<?php
/**
 * A WooCommerce plugin that allows users to virtually try on clothing and accessories.
 *
 * @package Try-On Tool
 * @copyright 2025 DataDove LTD
 * @license GPL-2.0-only
 *
 * This file is part of Try-On Tool.
 * 
 * Try-On Tool is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, version 2 only.
 * 
 * Try-On Tool is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */
use Aws\S3\S3Client;
use Aws\Exception\AwsException;

class WooFashnai_Wasabi {

    private static $s3 = null;
    private static $bucket = '';

    /**
     * Fetch Wasabi credentials in a secure way:
     *   1. POST license key + site URL to /wasabi/token  → receive short-lived token
     *   2. GET  /wasabi/secure-credentials?token=…      → receive bucket & keys
     */
    private static function fetch_credentials() {
        static $cached = null;
        if ( $cached ) { return $cached; }

        $license_key = get_option( 'woo_fashnai_license_key' );
        if ( ! $license_key ) {
            error_log( 'Wasabi Client: license key missing – cannot request token' );
            return false;
        }

        $site_url  = home_url();
        $token_url = 'https://tryontool.com/wp-json/tryontool/v1/wasabi/token';
        $token_resp = wp_remote_post( $token_url, array(
            'headers' => array( 'Content-Type' => 'application/json' ),
            'body'    => wp_json_encode( array(
                'license_key' => $license_key,
                'site_url'    => $site_url,
            ) ),
            'timeout' => 20,
        ) );

        if ( is_wp_error( $token_resp ) ) {
            error_log( 'Wasabi Client: token request error – ' . $token_resp->get_error_message() );
            return false;
        }

        $token_body = json_decode( wp_remote_retrieve_body( $token_resp ), true );
        if ( empty( $token_body['token'] ) ) {
            error_log( 'Wasabi Client: token not received – response: ' . print_r( $token_body, true ) );
            return false;
        }

        $secure_url = 'https://tryontool.com/wp-json/tryontool/v1/wasabi/secure-credentials?token=' . urlencode( $token_body['token'] );
        $cred_resp  = wp_remote_get( $secure_url, array( 'timeout' => 20 ) );

        if ( is_wp_error( $cred_resp ) ) {
            error_log( 'Wasabi Client: credential fetch error – ' . $cred_resp->get_error_message() );
            return false;
        }

        $cred_body = json_decode( wp_remote_retrieve_body( $cred_resp ), true );
        if ( isset( $cred_body['bucket'], $cred_body['access_key'], $cred_body['secret_key'] ) ) {
            $cached = $cred_body; // cache in static var for rest of request
            error_log( 'Wasabi Client Debug: Successfully received credentials from server' );
            return $cred_body;
        }

        error_log( 'Wasabi Client Error: invalid credential response – ' . print_r( $cred_body, true ) );
        return false;
    }

    public static function client() {
        if (self::$s3) { return self::$s3; }

        require_once WOO_FASHNAI_PREVIEW_PLUGIN_DIR . 'vendor/autoload.php';

        $credentials = self::fetch_credentials();
        if ($credentials) {
            self::$bucket = $credentials['bucket'];
            $key = $credentials['access_key'];
            $secret = $credentials['secret_key'];
            error_log('Wasabi Client Debug: Successfully fetched credentials for bucket: ' . self::$bucket);
        } else {
            error_log('Wasabi Client Error: Failed to fetch Wasabi credentials');
            return null;
        }

        // Wasabi buckets reside in a specific region.  The "us-east-1" region
        // together with the generic endpoint (s3.wasabisys.com) works for any
        // bucket that was created in the default region.  If your bucket lives
        // in a different region, adjust both the region string and the host
        // accordingly (e.g.  "eu-west-1"  →  "s3.eu-west-1.wasabisys.com").

        // self::$s3 = new S3Client([
        //     'version'                 => 'latest',
        //     'region'                  => 'us-east-1',
        //     'endpoint'                => 'https://s3.wasabisys.com',
        //     'use_path_style_endpoint' => true,
        //     'credentials'             => [ 'key' => $key, 'secret' => $secret ],
        // ]);

        self::$s3 = new S3Client( [
            'version'                => 'latest',
            'region'                 => 'eu-west-1',
            'endpoint'               => 'https://s3.eu-west-1.wasabisys.com',
            'use_path_style_endpoint'=> true,
            'credentials'            => [ 'key'=> $key, 'secret'=> $secret ],
        ] );

        return self::$s3;
    }

    public static function upload($user_id, $local_file) {
        $key = self::object_key($user_id, basename($local_file));
        $client = self::client();
        if (!$client) {
            error_log('Wasabi Upload Error: Failed to initialize client');
            return false;
        }
        
        error_log('Wasabi Upload Debug: Attempting to upload file: ' . $local_file);
        error_log('Wasabi Upload Debug: Bucket: ' . self::$bucket);
        error_log('Wasabi Upload Debug: Key: ' . $key);
        
        try {
            $client->putObject([
                'Bucket' => self::$bucket,
                'Key' => $key,
                'SourceFile' => $local_file,
                'ACL' => 'private',
                'ServerSideEncryption' => 'AES256',
            ]);

            // Store a reference so our cron/cleanup task can remove the file later.
            // IMPORTANT: the transient itself must NOT expire before we run the
            // cleanup callback – otherwise we lose the metadata required to
            // delete the image from Wasabi.  Therefore we save it **without**
            // an expiration (third argument = 0) and rely on our own time-
            // stamp check to determine when the object should be removed.

            $transient_key = 'woo_fashnai_image_deletion_' . md5( $key );
            $data = array(
                'user_id'   => $user_id,
                'key'       => $key,
                'timestamp' => time(), // when the file was uploaded
                'upload_time' => time(), // legacy field for older cleaners
                'image_url'   => self::public_url( $key ),
            );

            // Zero (0) = no auto-expiry; we handle removal later when the file is
            // older than our configured retention window (currently 12 months).
            set_transient( $transient_key, $data, 0 );

            /* ------------------------------------------------------------------
               BACK-COMPAT: some older cleanup jobs still look for transients
               that begin with  "woo_fashnai_image_" (without the "deletion"
               part) and, crucially, they expect the time-stamp to live in an
               "upload_time" field.  To keep those jobs working we create a
               shadow transient in that older format.
            ------------------------------------------------------------------ */

            $legacy_key = 'woo_fashnai_image_' . md5( $key );
            if ( ! get_transient( $legacy_key ) ) {
                set_transient( $legacy_key, $data, 0 );
            }
        } catch (AwsException $e) {
            error_log('Wasabi Upload Error: ' . $e->getMessage());
            return false;
        }
        return self::public_url($key);
    }

    public static function list_user_images($user_id) {
        $prefix = self::user_prefix($user_id);
        $client = self::client();
        if (!$client) {
            error_log('Wasabi Client: Failed to initialize client');
            return [];
        }

        try {
            error_log('Wasabi Client: Listing objects with prefix ' . $prefix);
            $objects = $client->listObjectsV2([
                'Bucket' => self::$bucket,
                'Prefix' => $prefix,
            ]);
            error_log('Wasabi Client: ListObjectsV2 response: ' . print_r($objects, true));
        } catch (AwsException $e) {
            error_log('Error with ListObjectsV2: ' . $e->getMessage());
            return [];
        }

        $urls = [];
        if (!empty($objects['Contents'])) {
            foreach ($objects['Contents'] as $obj) {
                $urls[] = self::public_url($obj['Key']);
            }
        } else {
            error_log('Wasabi Client: No objects found with prefix ' . $prefix);
        }
        return $urls;
    }

    public static function delete( $key ) {
        $client = self::client();
        if ( ! $client ) {
            return false;
        }

        // Remember the prefix ("folder") so we can recreate a placeholder if empty
        $prefix = '';
        if ( false !== strpos( $key, '/' ) ) {
            $prefix = rtrim( dirname( $key ), '/' ) . '/';
        }

        try {
            $client->deleteObject( [
                'Bucket' => self::$bucket,
                'Key'    => $key,
            ] );

            // If we have a prefix, check whether it's now empty; if so, add a ".keep" file
            if ( $prefix !== '' ) {
                $objects = $client->listObjectsV2( [
                    'Bucket'  => self::$bucket,
                    'Prefix'  => $prefix,
                    'MaxKeys' => 1, // we only need to know if at least one object exists
                ] );

                $is_empty = empty( $objects['Contents'] );

                if ( $is_empty ) {
                    // Create a zero-byte placeholder so the "folder" stays visible in consoles.
                    $placeholder_key = $prefix . '.keep';
                    $client->putObject( [
                        'Bucket'      => self::$bucket,
                        'Key'         => $placeholder_key,
                        'Body'        => '',
                        'ACL'         => 'private',
                        'ContentType' => 'text/plain',
                    ] );
                }
            }
        } catch ( AwsException $e ) {
            error_log( 'Wasabi Delete Error: ' . $e->getMessage() );
            return false;
        }

        return true;
    }

    /* ───── Helpers ───── */
    private static function user_prefix($user_id) {
        $site_url = parse_url(home_url(), PHP_URL_HOST); // Get the host part of the site URL
        $user = get_userdata($user_id);
        $email = $user ? $user->user_email : 'unknown';
        return $site_url . '/' . $user_id . '-' . $email . '/';
    }
    private static function object_key($user_id, $file_name) {
        $site_url = parse_url(home_url(), PHP_URL_HOST); // Get the host part of the site URL
        $user = get_userdata($user_id);
        $email = $user ? $user->user_email : 'unknown';
        $base = pathinfo($file_name, PATHINFO_FILENAME);
        return $site_url . '/' . $user_id . '-' . $email . '/' . time() . '_' . $base . '.jpg';
    }
    private static function public_url( $key ) {
        return 'https://s3.eu-west-1.wasabisys.com/' . self::$bucket . '/' . $key;
    }

    public static function bucket(){return self::$bucket;}
}

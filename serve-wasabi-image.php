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
// serve-wasabi-image.php

// Load WordPress and plugin context
require_once __DIR__ . '/wp-load.php'; // Adjust path if needed

// Security: Only allow logged-in users (optional, remove if public)
// if (!is_user_logged_in()) {
//     http_response_code(403);
//     exit('Forbidden');
// }

if (!isset($_GET['key'])) {
    http_response_code(400);
    exit('Missing key');
}

$key = $_GET['key'];

require_once __DIR__ . '/includes/class-wasabi-client.php';
$s3 = WooFashnai_Wasabi::client();
$bucket = WooFashnai_Wasabi::bucket();

try {
    $result = $s3->getObject([
        'Bucket' => $bucket,
        'Key'    => $key,
    ]);
    $imageData = $result['Body'];
} catch (Exception $e) {
    http_response_code(404);
    exit('Image not found');
}

// Save to temp file for type detection/conversion
$tmpFile = tempnam(sys_get_temp_dir(), 'wasabiimg');
file_put_contents($tmpFile, $imageData);

$imageType = @exif_imagetype($tmpFile);
if ($imageType !== IMAGETYPE_JPEG) {
    // Convert to JPEG
    switch ($imageType) {
        case IMAGETYPE_PNG:
            $src = imagecreatefrompng($tmpFile);
            break;
        case IMAGETYPE_GIF:
            $src = imagecreatefromgif($tmpFile);
            break;
        case IMAGETYPE_WEBP:
            $src = function_exists('imagecreatefromwebp') ? imagecreatefromwebp($tmpFile) : false;
            break;
        default:
            $src = imagecreatefromstring(file_get_contents($tmpFile));
    }
    if ($src) {
        header('Content-Type: image/jpeg');
        imagejpeg($src, null, 90);
        imagedestroy($src);
        @unlink($tmpFile);
        exit;
    }
}
// If already JPEG, just output
header('Content-Type: image/jpeg');
readfile($tmpFile);
@unlink($tmpFile);
exit; 
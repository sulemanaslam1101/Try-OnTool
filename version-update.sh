#!/bin/bash

# Version Update Script for Try-On Tool Plugin
# Usage: ./version-update.sh <new-version>

if [ $# -eq 0 ]; then
    echo "Usage: ./version-update.sh <new-version>"
    echo "Example: ./version-update.sh 1.1.0"
    exit 1
fi

NEW_VERSION=$1
CURRENT_DATE=$(date +"%Y-%m-%d")

echo "Updating version to $NEW_VERSION..."

# Update main plugin file
sed -i "s/Version: [0-9]\+\.[0-9]\+\.[0-9]*/Version: $NEW_VERSION/" woo-fashnai-preview.php
sed -i "s/define('WOO_FASHNAI_PREVIEW_VERSION', '[0-9]\+\.[0-9]\+\.[0-9]*');/define('WOO_FASHNAI_PREVIEW_VERSION', '$NEW_VERSION');/" woo-fashnai-preview.php

# Update modification date
sed -i "s/\/\/ Modified by DataDove LTD on [0-9]\{4\}-[0-9]\{2\}-[0-9]\{2\}/\/\/ Modified by DataDove LTD on $CURRENT_DATE/" woo-fashnai-preview.php

echo "✅ Updated woo-fashnai-preview.php"

# Update CHANGELOG.md
if [ -f "CHANGELOG.md" ]; then
    # Add new version entry at the top
    sed -i "1i ## [$NEW_VERSION] - $CURRENT_DATE\n\n### Added\n- \n\n### Changed\n- \n\n### Fixed\n- \n" CHANGELOG.md
    echo "✅ Updated CHANGELOG.md"
fi

# Update RELEASE_CHECKLIST.md
if [ -f "RELEASE_CHECKLIST.md" ]; then
    sed -i "s/# Release Checklist - Version [0-9]\+\.[0-9]\+/# Release Checklist - Version $NEW_VERSION/" RELEASE_CHECKLIST.md
    echo "✅ Updated RELEASE_CHECKLIST.md"
fi

# Update VERSION_CONTROL.md
if [ -f "VERSION_CONTROL.md" ]; then
    sed -i "s/### Current Version: [0-9]\+\.[0-9]\+\.[0-9]*/### Current Version: $NEW_VERSION/" VERSION_CONTROL.md
    echo "✅ Updated VERSION_CONTROL.md"
fi

echo ""
echo "🎉 Version updated to $NEW_VERSION"
echo "📝 Don't forget to:"
echo "   - Review the changes"
echo "   - Test the plugin"
echo "   - Update any additional version references"
echo "   - Commit changes: git add . && git commit -m \"Update version to $NEW_VERSION\"" 
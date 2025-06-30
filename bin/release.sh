#!/bin/bash

# Axytos WooCommerce Plugin Release Script
# Creates a WordPress-ready zip file for plugin distribution

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Get the directory where this script is located
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_DIR="$(dirname "$SCRIPT_DIR")"
PLUGIN_NAME="axytos-woocommerce"

echo -e "${GREEN}Starting release build for Axytos WooCommerce Plugin${NC}"

# Extract version from main plugin file
VERSION=$(grep "Version:" "$PLUGIN_DIR/axytos-woocommerce.php" | sed 's/.*Version: *\([0-9.]*\).*/\1/')

if [ -z "$VERSION" ]; then
    echo -e "${RED}Error: Could not extract version from plugin file${NC}"
    exit 1
fi

echo -e "${YELLOW}Plugin version: $VERSION${NC}"

# Create temporary build directory
BUILD_DIR="/tmp/axytos-wc-build-$$"
RELEASE_DIR="$BUILD_DIR/$PLUGIN_NAME"
mkdir -p "$RELEASE_DIR"

echo -e "${YELLOW}Creating build directory: $BUILD_DIR${NC}"

# Copy core files
echo -e "${YELLOW}Copying core files...${NC}"
cp "$PLUGIN_DIR/axytos-woocommerce.php" "$RELEASE_DIR/"
cp "$PLUGIN_DIR/index.php" "$RELEASE_DIR/"
cp "$PLUGIN_DIR/README.md" "$RELEASE_DIR/"
if [ -f "$PLUGIN_DIR/CHANGELOG.md" ]; then
    cp "$PLUGIN_DIR/CHANGELOG.md" "$RELEASE_DIR/"
fi

# Note: composer.json and composer.lock excluded as they contain only dev dependencies

# Copy directories
echo -e "${YELLOW}Copying includes directory...${NC}"
if [ -d "$PLUGIN_DIR/includes" ]; then
    cp -r "$PLUGIN_DIR/includes" "$RELEASE_DIR/"
    # Remove backup files
    find "$RELEASE_DIR/includes" -name "*.php~" -delete
    find "$RELEASE_DIR/includes" -name "*~" -delete
fi

echo -e "${YELLOW}Copying assets directory...${NC}"
if [ -d "$PLUGIN_DIR/assets" ]; then
    cp -r "$PLUGIN_DIR/assets" "$RELEASE_DIR/"
fi

echo -e "${YELLOW}Copying languages directory...${NC}"
if [ -d "$PLUGIN_DIR/languages" ]; then
    cp -r "$PLUGIN_DIR/languages" "$RELEASE_DIR/"
fi

# Note: vendor directory is excluded as it contains only dev dependencies

# Create releases directory if it doesn't exist
RELEASES_DIR="$PLUGIN_DIR/releases"
mkdir -p "$RELEASES_DIR"

# Create zip file
ZIP_NAME="$PLUGIN_NAME-$VERSION.zip"
ZIP_PATH="$RELEASES_DIR/$ZIP_NAME"

echo -e "${YELLOW}Creating zip file: $ZIP_NAME${NC}"

# Change to build directory and create zip
cd "$BUILD_DIR"
zip -r "$ZIP_PATH" "$PLUGIN_NAME" -x "*.DS_Store" "*/.*"

# Cleanup
rm -rf "$BUILD_DIR"

# Verify zip file
if [ -f "$ZIP_PATH" ]; then
    FILE_SIZE=$(du -h "$ZIP_PATH" | cut -f1)
    echo -e "${GREEN}✓ Release zip created successfully!${NC}"
    echo -e "${GREEN}  File: $ZIP_PATH${NC}"
    echo -e "${GREEN}  Size: $FILE_SIZE${NC}"
    echo ""
    echo -e "${YELLOW}Zip contents:${NC}"
    unzip -l "$ZIP_PATH" | head -20
    
    # Show total files
    TOTAL_FILES=$(unzip -l "$ZIP_PATH" | tail -1 | awk '{print $2}')
    echo -e "${YELLOW}Total files: $TOTAL_FILES${NC}"
    
    echo ""
    echo -e "${GREEN}The zip file is ready for WordPress installation!${NC}"
    echo -e "${YELLOW}You can upload this file via WordPress Admin > Plugins > Add New > Upload Plugin${NC}"
else
    echo -e "${RED}Error: Failed to create zip file${NC}"
    exit 1
fi
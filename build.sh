#!/bin/bash

# Mollie VirtueMart Package Build Script
# Creates a Joomla-installable ZIP package

# Configuration
PACKAGE_NAME="pkg_mollie"
VERSION="1.0.1"
OUTPUT_FILE="${PACKAGE_NAME}_${VERSION}.zip"
TEMP_DIR="build_temp"

# Clean up old builds
echo "Cleaning up old builds..."
rm -f "$OUTPUT_FILE" com_mollie.zip plg_vmpayment_mollie.zip
rm -rf "$TEMP_DIR"

mkdir -p "$TEMP_DIR"

echo "Building Mollie VirtueMart package..."
echo ""

# Build component ZIP
echo "1. Building component (com_mollie.zip)..."
# Create a temporary component structure using Joomla standard
mkdir -p "$TEMP_DIR/component_tmp/admin"
mkdir -p "$TEMP_DIR/component_tmp/media/com_mollie"

# Copy administrator files to admin/
cp -r administrator/components/com_mollie/* "$TEMP_DIR/component_tmp/admin/"

# Copy media files
cp -r media/com_mollie/* "$TEMP_DIR/component_tmp/media/com_mollie/"

# Create the component ZIP with proper structure
cd "$TEMP_DIR/component_tmp"
zip -r "../com_mollie.zip" \
    admin \
    media \
    -x "*.git*" \
    -x "*.DS_Store"
cd ../..
rm -rf "$TEMP_DIR/component_tmp"
echo "   ✓ Component ZIP created (Joomla standard structure)"

# Build plugin ZIP
echo "2. Building plugin (plg_vmpayment_mollie.zip)..."
cd plugin/vmpayment/mollie
zip -r "../../../$TEMP_DIR/plg_vmpayment_mollie.zip" \
    . \
    -x "*.git*" \
    -x "*.DS_Store"
cd ../../..
echo "   ✓ Plugin ZIP created"

# Build package ZIP
echo "3. Building package (${OUTPUT_FILE})..."
cd "$TEMP_DIR"
zip -r "../$OUTPUT_FILE" \
    com_mollie.zip \
    plg_vmpayment_mollie.zip
cd ..
zip -j "$OUTPUT_FILE" pkg_mollie.xml
echo "   ✓ Package ZIP created"

# Clean up temp directory
rm -rf "$TEMP_DIR"

echo ""
if [ -f "$OUTPUT_FILE" ]; then
    echo "✓ Build successful!"
    echo "✓ Package: $OUTPUT_FILE"
    echo "✓ Size: $(du -h "$OUTPUT_FILE" | cut -f1)"
    echo ""
    echo "Install via:"
    echo "  Joomla Administrator > Extensions > Install > Upload Package File"
else
    echo "✗ Error: Failed to create package"
    exit 1
fi

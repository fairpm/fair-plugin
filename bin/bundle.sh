#!/bin/bash -e
#
# Create our distribution zips.

SCRIPT_DIR=$( cd -- "$( dirname -- "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )

# Prepare the distribution directory.
DIST_DIR="/tmp/fair-dist"
[ -d "$DIST_DIR" ] && rm -rf "$DIST_DIR"
mkdir "$DIST_DIR"
touch /tmp/fair-dist/MD5SUMS
touch /tmp/fair-dist/SHA1SUMS
touch /tmp/fair-dist/SHA256SUMS
touch /tmp/fair-dist/SHA384SUMS

# Bundle our plugin first.
[ -d /tmp/fair-temp ] && rm -rf /tmp/fair-temp
mkdir -p /tmp/fair-temp/wordpress/wp-content/plugins/fair-plugin
rsync -a --exclude-from="$SCRIPT_DIR/../.distignore" "$SCRIPT_DIR/.." /tmp/fair-temp/wordpress/wp-content/plugins/fair-plugin

echo "Fetching WordPress version data" >&2
VERSION_DATA=$(curl -s https://api.wordpress.org/core/version-check/1.7/)
AVAILABLE_VERSIONS=$(echo "$VERSION_DATA" | jq -r '.offers[] | .version')

# For each available version, download WP and add our plugin to the WP zip.
for VERSION in $AVAILABLE_VERSIONS; do
	# Skip repeat versions via the API.
	if [ -f "$DIST_DIR/wordpress-$VERSION-fair.zip" ]; then
		echo "Skipping $VERSION (already bundled)" >&2
		continue
	fi

	echo "Bundling $VERSION..." >&2
	# Download the WP zip.
	WP_ZIP_URL="https://wordpress.org/wordpress-$VERSION.zip"
	WP_ZIP_FILE="/tmp/fair-temp/wordpress-$VERSION.zip"
	echo "  Downloading $WP_ZIP_URL..." >&2
	curl -sSL "$WP_ZIP_URL" -o "$WP_ZIP_FILE"
	EXPECTED_HASH=$(curl -sSL "$WP_ZIP_URL.sha1")

	# Verify the checksum.
	# (sha1 is suboptimal, but it's all we've got.)
	echo "  Verifying checksum" >&2
	echo "$EXPECTED_HASH *$WP_ZIP_FILE" | sha1sum -c --status - || { echo "Checksum verification failed!" >&2; exit 1; }

	# Add the plugin into the existing zip.
	echo "  Adding plugin" >&2
	cd /tmp/fair-temp
	zip -r /tmp/fair-temp/wordpress-$VERSION.zip wordpress/ >&2
	cd - > /dev/null

	# Rename altered zip.
	mv /tmp/fair-temp/wordpress-$VERSION.zip "$DIST_DIR/wordpress-$VERSION-fair.zip"

	# Recalculate hashes.
	echo "  Calculating hashes" >&2
	cd "$DIST_DIR"
	md5sum -b "wordpress-$VERSION-fair.zip" >> /tmp/fair-dist/MD5SUMS
	sha1sum -b "wordpress-$VERSION-fair.zip" >> /tmp/fair-dist/SHA1SUMS
	sha256sum -b "wordpress-$VERSION-fair.zip" >> /tmp/fair-dist/SHA256SUMS
	sha384sum -b "wordpress-$VERSION-fair.zip" >> /tmp/fair-dist/SHA384SUMS
	cd - > /dev/null

	# Output filename.
	echo $DIST_DIR/wordpress-$VERSION-fair.zip
done

# Clean up.
rm -rf /tmp/fair-temp

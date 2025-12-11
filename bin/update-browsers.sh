#!/bin/bash
#
# Update the browser regex in the version check script.
#
# Uses a browserslist query to generate this data.
# Needs you to run `npx browserslist-useragent-regexp "defaults, unreleased versions"` before first use
set  -eo pipefail

BROWSER_QUERY="defaults, unreleased versions"

SCRIPT_DIR=$( cd -- "$( dirname -- "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )
VERSION_CHECK_FILE="$SCRIPT_DIR/../inc/version-check/namespace.php"

LINE_START="const BROWSER_REGEX = ";

echo "Fetching regex for query: '$BROWSER_QUERY'" >&2
REGEX=$(npx browserslist-useragent-regexp "$BROWSER_QUERY")
echo "  $REGEX" >&2

# Double-escape backslashes.
ESCAPED="$(printf '%s\n' "$REGEX" | sed 's/\\/\\\\/g')"

# Replace the line in the file.
echo "Updating $VERSION_CHECK_FILE" >&2
sed -i.bak "s#$LINE_START.*#$LINE_START'$ESCAPED';#g" "$VERSION_CHECK_FILE"
echo "Updated! (Backup saved to $VERSION_CHECK_FILE.bak)" >&2

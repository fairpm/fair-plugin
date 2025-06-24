#!/bin/bash -e
#
# Create our distribution zips.

# Create a link to our commit-msg hook.
ln -s -f ../../.githooks/commit-msg .git/hooks/commit-msg
echo "Installed commit-msg hook."

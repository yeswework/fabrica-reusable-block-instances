#!/bin/sh

# based on https://learnwithdaniel.com/2019/09/publishing-your-first-wordpress-plugin-with-git-and-svn/
# requires a local svn clone at <path>
# eg. `svn checkout https://yeswework@plugins.svn.wordpress.org/fabrica-reusable-block-instances/ <path>`

if [[ $# -lt 2 ]]; then
	echo " › usage: $0 <path> <comment>\n"
	echo " › where <path> is the path to the release/svn root folder"
	echo " › <path> is the svn commit comment\n"
	exit 1
fi

version=`sed -n "s/Version: \(.*\)/\1/p" "fabrica-reusable-block-instances.php"`

echo " › updating svn trunk (version $version)..."
rsync -rc --exclude-from=".distignore" "./" "$1/trunk/" --delete --delete-excluded

cd "$1"
svn add . --force
echo "\n › svn status:"
svn status | grep '^\!' | sed 's/! *//' | xargs -I% svn rm %@

echo "\n › tagging v.$version..."
svn cp "trunk" "tags/$version"

# fix screenshots MIME types (to avoid downloading them when clicking)
svn propset svn:mime-type image/png assets/*.png || true
# add if necessary: svn propset svn:mime-type image/jpeg assets/*.jpg || true

echo "\n › submitting with comment: '$version: $2'..."
svn ci --username yeswework -m "v.$version: $2"

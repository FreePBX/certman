#!/bin/bash -x

# This script should be run when packaging certman, to update the
# embedded acme.sh tgz.

MODULEDIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )"/.. >/dev/null && pwd )"

# Delete any old packages
rm -f $MODULEDIR/acme.sh-*

WORKDIR=$(mktemp -d)
cd $WORKDIR
git clone --depth=1 https://github.com/Neilpang/acme.sh.git

# Get the hash of this checkout
cd acme.sh
GITHASH=$(git rev-parse --short HEAD)
echo $GITHASH > githash

# Remove .git stuff that doesn't need to be packaged
rm -rf .git .github .travis.yml

cd $WORKDIR

# Make the builds reproducable, by pinning the time and owner
tar --mtime="2018-01-01" --owner=0 --group=0 -cf $MODULEDIR/acme.sh-bundled.tar acme.sh
# if you use 'tar zcvf' it adds a timestamp of when the package was built. We don't
# want that, so we gzip it seperately
gzip --no-name $MODULEDIR/acme.sh-bundled.tar

cd $MODULEDIR
rm -rf $WORKDIR


#!/bin/bash
# This script copies a deployment config over the root directory (installing it),
# and then generates necessary secrets if they are not yet existing.
# To avoid re-generating secrets store them in a config.secrets file, which will be appended to the .env file before checking
# for existing secrets.
# This script is no longer used if containers are used as the webapp container will overlay the config itself.

# Uninstall the old config
if [ -d config ]; then
    echo "Uninstalling the old config."
    find -L config/ -type f | while read file; do
        file=$(echo $file | sed -e 's|^config||g')
        file="./$file"

        rm -v $file
    done
fi

if [ "$1" == "" ]; then
    echo "Failed to find the configuration folder, please pass one as argument (e.g. config.demo)."
    exit 1
fi

if [ ! -d $1 ]; then
    echo "Failed to find the configuration folder, please pass one as argument (e.g. config.demo)."
    exit 1
fi

echo "Installing $1."
# Link new config
rm config
ln -s $1 config

# Install new config
find -L config/ -type f | while read file; do
    dir=$(dirname $file | sed -e 's|^config||g')
    dir="./$dir"

    if [ ! -d $dir ]; then
        mkdir -p $dir
    fi

    cp -v $file $dir/
done


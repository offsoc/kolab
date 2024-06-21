#!/bin/bash
cp src/.env .env.reconfigure.backup

#FIXME: this will not remove files that have been removed in the updated overlay

# Install new config
find -L config/ -type f | while read file; do
    dir=$(dirname $file | sed -e 's|^config||g')
    dir="./$dir"

    if [ ! -d $dir ]; then
        mkdir -p $dir
    fi

    cp -v $file $dir/
done

mv .env.reconfigure.backup src/.env

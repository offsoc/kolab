#!/bin/bash
echo "Listing changes since commit $1"

echo
echo "Kolab 4 changes: "
git log --oneline $1..master -- src/
echo
echo "Container changes: "
git log --oneline $1..master -- docker
echo
echo "Migrations: "
git log --oneline $1..master -- src/database/migrations/

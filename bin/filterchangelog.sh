#!/bin/bash
echo "Listing changes since commit $1"

echo
echo "Kolab 4 changes: "
git log --oneline $1..HEAD -- src/
echo
echo "Container changes: "
git log --oneline $1..HEAD -- docker
echo
echo "Migrations: "
git log --oneline $1..HEAD -- src/database/migrations/

#!/bin/bash

# Resurs Bank AB plugin for WooCommerce merge-script. Converts the new repo content to a compatible non-disabled
# module for the official repo.

old=resurs-bank-payment-gateway-for-woocommerce
new=tornevall-networks-resurs-bank-payment-gateway-for-woocommerce

oldbranch=develop/3.0
newbranch=develop/1.0

# Set this to 1 if you want to see something in the copy/move process
verbose=""
commitAndPush=0

if [ "push" = "$1" ] ; then
    commitAndPush=1
fi

if [ ! -d $old ] && [ ! -d $new ] ; then
    echo "The directories ${old} and ${new} missing in your file structure."
    exit
fi

whereami=$(pwd | grep $new)
if [ "" != "$whereami" ] ; then
    echo "It seems that this script is running within a codebase. Not good. Please exit this directory or try again."
    exit
fi

echo "Preparing branches..."

echo "Refresh ${old}"
cd ${old}

echo "Branch control (master)"
curbranch=$(git branch | grep "^*")

echo "Current branch is ${curbranch}"
if [ "$curbranch" != "* master" ] ; then
    echo "And that was not right. Trying to restore state of branches."
    git reset --hard && git clean -f -d && git checkout master
else
    echo "And that seem to be correct ..."
fi

curbranch=$(git branch | grep "^*")

if [ "$curbranch" != "* master" ] ; then
    echo "Something failed during checkout. Aborting!"
    exit
fi

echo "Current branch is now ${curbranch} ... Syncing!"

git fetch --all -p
git pull
echo "Going for ${oldbranch} in ${old}"
git checkout ${oldbranch}
git fetch --all -p
git pull

curbranch=$(git branch | grep "^*")
echo "Current branch is now ${curbranch}"

if [ "$curbranch" != "* $oldbranch" ] ; then
    echo "I am not in the correct branch. Aborting!"
    exit
fi

echo "Cleaning up ..."
find . | \
    grep -v .git| \
    grep -v ^.$ | \
    grep -v ^..$ | \
    awk '{system("rm -rf \"" $1 "\"")}'

echo "Refresh ${new}"

cd ../${new}
git checkout master
git fetch --all -p
git pull
echo "Going back to ${newbranch} for ${new}"
git checkout ${newbranch}
git fetch --all -p
git pull

echo "Ok. Now going for the correct source code..."

find . -maxdepth 1 | \
    grep -v .git | \
    grep -v "^.$" | \
    awk '{system("cp -rf \"" $1 "\" ../resurs-bank-payment-gateway-for-woocommerce/")}'

echo "Going back to old branch..."

cd ../${old}
echo "Old branch goes back to master..."

mv ${verbose} init.php resursbankgateway.php
sed -i 's/Plugin Name: Tornevall Networks Resurs Bank payment gateway/Plugin Name: Resurs Bank Payment Gateway/' \
    resursbankgateway.php readme.txt
sed -i 's/= Tornevall Networks Resurs Bank payment gateway/= Resurs Bank Payment Gateway/' \
    resursbankgateway.php readme.txt
sed -i 's/tornevall-networks-resurs-bank-payment-gateway-for-woocommerce/resurs-bank-payment-gateway-for-woocommerce/' \
    *.php includes/Resursbank/*.php includes/Resursbank/Helpers/*.php

languages="da_DK en_GB nb_NO sv_SE"
for lang in $languages
do
    oldfile="languages/tornevall-networks-resurs-bank-payment-gateway-for-woocommerce-${lang}."
    newfile="languages/resurs-bank-payment-gateway-for-woocommerce-${lang}."

    if [ -f ${oldfile}mo ] ; then
        mv ${verbose} ${oldfile}mo ${newfile}mo
        mv ${verbose} ${oldfile}po ${newfile}po
    fi
done

if [ "$commitAndPush" = "1" ] ; then
    git commit -a -m "Automatic repo- and fork synchronization made with migrate.sh"
    git push -u origin $oldbranch
    git checkout master
else
    echo ""
    echo "All done! You're left within the branch ${oldbranch} in the Resurs Bank repo as no push has been requested."
    echo "To really push something, you should run this script with 'push' as an extra argument."
fi

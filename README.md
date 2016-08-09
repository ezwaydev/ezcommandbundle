# EzWay/EzCommandBundle

My very first bundle to learn and test concepts of ezsystems/ezplatform.

## Install

Modify your project composer.json, this is not at packagist.org

	"repositories" : [{
			"type" : "vcs",
			"url" : "https://github.com/ezwaydev/ezcommandbundle.git"
		}]

Add to app/AppKernel.php

	new EzWay\EzCommandBundle\EzWayEzCommandBundle(),
	
Require package

	composer require ezway/ezcommandbundle
	

## Commands

### ez:user
Get command help

	php app/console help ez:user

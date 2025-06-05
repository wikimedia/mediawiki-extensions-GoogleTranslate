<?php

namespace MediaWiki\Extension\GoogleTranslate\Tests;

class BundleSizeTest extends \MediaWiki\Tests\Structure\BundleSizeTestBase {

	/** @inheritDoc */
	public static function getBundleSizeConfigData(): string {
		return dirname( __DIR__, 2 ) . '/bundlesize.config.json';
	}
}

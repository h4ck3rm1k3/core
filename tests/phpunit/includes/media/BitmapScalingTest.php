<?php

class BitmapScalingTest extends MediaWikiTestCase {

	function setUp() {
		global $wgMaxImageArea, $wgCustomConvertCommand;
		$this->oldMaxImageArea = $wgMaxImageArea;
		$this->oldCustomConvertCommand = $wgCustomConvertCommand;
		$wgMaxImageArea = 1.25e7; // 3500x3500 
		$wgCustomConvertCommand = 'dummy'; // Set so that we don't get client side rendering
	}
	function tearDown() {
		global $wgMaxImageArea, $wgCustomConvertCommand;
		$wgMaxImageArea = $this->oldMaxImageArea;
		$wgCustomConvertCommand = $this->oldCustomConvertCommand;
	}
	/**
	 * @dataProvider provideNormaliseParams
	 */
	function testNormaliseParams( $fileDimensions, $expectedParams, $params, $msg ) {
		$file = new FakeDimensionFile( $fileDimensions );
		$handler = new BitmapHandler;
		$valid = $handler->normaliseParams( $file, $params );
		$this->assertTrue( $valid );
		$this->assertEquals( $expectedParams, $params, $msg );
	}
	
	function provideNormaliseParams() {
		return array(
			/* Regular resize operations */	
			array(
				array( 1024, 768 ),
				array( 
					'width' => 512, 'height' => 384, 
					'physicalWidth' => 512, 'physicalHeight' => 384,
					'page' => 1,
				),
				array( 'width' => 512 ),
				'Resizing with width set',
			),
			array(
				array( 1024, 768 ),
				array( 
					'width' => 512, 'height' => 384, 
					'physicalWidth' => 512, 'physicalHeight' => 384,
					'page' => 1, 
				),
				array( 'width' => 512, 'height' => 768 ),
				'Resizing with height set too high',
			),
			array(
				array( 1024, 768 ),
				array( 
					'width' => 512, 'height' => 384, 
					'physicalWidth' => 512, 'physicalHeight' => 384,
					'page' => 1, 
				),
				array( 'width' => 1024, 'height' => 384 ),
				'Resizing with height set',
			),
			
			/* Very tall images */
			array(
				array( 1000, 100 ),
				array( 
					'width' => 5, 'height' => 1,
					'physicalWidth' => 5, 'physicalHeight' => 1,
					'page' => 1, 
				),
				array( 'width' => 5 ),
				'Very wide image',
			),
			
			array(
				array( 100, 1000 ),
				array( 
					'width' => 1, 'height' => 10,
					'physicalWidth' => 1, 'physicalHeight' => 10,
					'page' => 1, 
				),
				array( 'width' => 1 ),
				'Very high image',
			),
			array(
				array( 100, 1000 ),
				array( 
					'width' => 1, 'height' => 5,
					'physicalWidth' => 1, 'physicalHeight' => 10,
					'page' => 1, 
				),
				array( 'width' => 10, 'height' => 5 ),
				'Very high image with height set',
			),
			/* Max image area */
			array(
				array( 4000, 4000 ),
				array( 
					'width' => 5000, 'height' => 5000,
					'physicalWidth' => 4000, 'physicalHeight' => 4000,
					'page' => 1, 
				),
				array( 'width' => 5000 ),
				'Bigger than max image size but doesn\'t need scaling',
			),
		);
	} 
	function testTooBigImage() {
		$file = new FakeDimensionFile( array( 4000, 4000 ) );
		$handler = new BitmapHandler;
		$params = array( 'width' => '3700' ); // Still bigger than max size.
		$this->assertEquals( 'TransformParameterError', 
			get_class( $handler->doTransform( $file, 'dummy path', '', $params ) ) );
	}
	function testTooBigMustRenderImage() {
		$file = new FakeDimensionFile( array( 4000, 4000 ) );
		$file->mustRender = true;
		$handler = new BitmapHandler;
		$params = array( 'width' => '5000' ); // Still bigger than max size.
		$this->assertEquals( 'TransformParameterError', 
			get_class( $handler->doTransform( $file, 'dummy path', '', $params ) ) );
	}
	
	function testImageArea() {
		$file = new FakeDimensionFile( array( 7, 9 ) );
		$handler = new BitmapHandler;
		$this->assertEquals( 63, $handler->getImageArea( $file ) );
	}
}

class FakeDimensionFile extends File {
	public $mustRender = false;

	public function __construct( $dimensions ) {
		parent::__construct( Title::makeTitle( NS_FILE, 'Test' ), 
			new NullRepo( null ) );
		
		$this->dimensions = $dimensions;
	}
	public function getWidth( $page = 1 ) {
		return $this->dimensions[0];
	}
	public function getHeight( $page = 1 ) {
		return $this->dimensions[1];
	}
	public function mustRender() {
		return $this->mustRender;
	}
	public function getPath() {
		return '';
	}
}

<?php
/**
 * WPSEO plugin test file.
 *
 * @package Yoast\WP\SEO\Tests\Generators\Schema
 */

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Yoast\WP\SEO\Generators\Schema\Person;
use Yoast\WP\SEO\Helpers\Image_Helper;
use Yoast\WP\SEO\Helpers\Schema\HTML_Helper;
use Yoast\WP\SEO\Helpers\Schema\ID_Helper;
use Yoast\WP\SEO\Helpers\Schema\Image_Helper as Schema_Image_Helper;
use Yoast\WP\SEO\Tests\Mocks\Indexable;
use Yoast\WP\SEO\Tests\Mocks\Meta_Tags_Context;
use Yoast\WP\SEO\Tests\TestCase;

/**
 * Class Person_Test
 *
 * @group generators
 * @group schema
 * @coversDefaultClass \Yoast\WP\SEO\Generators\Schema\Person
 */
class Person_Test extends TestCase {

	/**
	 * The instance to test.
	 *
	 * @var Person
	 */
	protected $instance;

	/**
	 * The meta tags context.
	 *
	 * @var Meta_Tags_Context
	 */
	protected $context;

	/**
	 * The ID helper.
	 *
	 * @var ID_Helper|Mockery\MockInterface
	 */
	protected $id;

	/**
	 * The image helper.
	 *
	 * @var Image_Helper|Mockery\MockInterface
	 */
	protected $image;

	/**
	 * The schema image helper.
	 *
	 * @var Schema_Image_Helper|Mockery\MockInterface
	 */
	protected $schema_image;

	/**
	 * The HTML helper.
	 *
	 * @var HTML_Helper
	 */
	protected $html;

	/**
	 * The social profiles. Should be a copy of $social_profiles in Person.
	 *
	 * @var string[]
	 */
	protected $social_profiles = [
		'facebook',
		'instagram',
		'linkedin',
		'pinterest',
		'twitter',
		'myspace',
		'youtube',
		'soundcloud',
		'tumblr',
		'wikipedia',
	];

	/**
	 * Initializes the test environment.
	 */
	public function setUp() {
		parent::setUp();

		$this->image        = Mockery::mock( Image_Helper::class );
		$this->schema_image = Mockery::mock( Schema_Image_Helper::class );
		$this->html         = Mockery::mock( HTML_Helper::class );
		$this->id           = Mockery::mock( ID_Helper::class );

		$this->instance = new Person( $this->image, $this->schema_image, $this->html );

		$this->instance->set_id_helper( $this->id );

		$this->context            = new Meta_Tags_Context();
		$this->context->indexable = new Indexable();
	}

	/**
	 * Tests whether generate returns the expected schema.
	 *
	 * @covers ::__construct
	 * @covers ::generate
	 * @covers ::determine_user_id
	 * @covers ::build_person_data
	 * @covers ::add_image
	 * @covers ::set_image_from_options
	 * @covers ::set_image_from_avatar
	 * @covers ::get_social_profiles
	 * @covers ::url_social_site
	 */
	public function test_generate_happy_path() {
		$this->context->site_user_id    = 1337;
		$this->context->site_url        = 'https://example.com/';
		$this->context->site_represents = 'person';

		$user_data             = (object) [
			'display_name' => 'John',
			'description'  => 'Description',
		];
		$person_logo_id        = 42;
		$person_schema_logo_id = $this->context->site_url . $this->id->person_logo_hash;
		$image_schema          = [
			'@type'      => 'ImageObject',
			'@id'        => $person_schema_logo_id,
			'inLanguage' => 'en-US',
			'url'        => 'https://example.com/image.png',
			'width'      => 64,
			'height'     => 128,
			'caption'    => 'Person image',
		];

		$expected = [
			'@type'       => [ 'Person', 'Organization' ],
			'@id'         => 'person_id',
			'name'        => 'John',
			'logo'        => [ '@id' => 'https://example.com/#personlogo' ],
			'description' => 'Description',
			'sameAs'      => [
				'https://example.com/social/facebook',
				'https://example.com/social/instagram',
				'https://example.com/social/linkedin',
				'https://example.com/social/pinterest',
				'https://twitter.com/https://example.com/social/twitter',
				'https://example.com/social/myspace',
				'https://example.com/social/youtube',
				'https://example.com/social/soundcloud',
				'https://example.com/social/tumblr',
				'https://example.com/social/wikipedia',
			],
			'image'       => $image_schema,
		];

		$this->expects_for_determine_user_id();
		$this->expects_for_get_userdata( $user_data );

		// Tests for the method `set_image_from_options`.
		$this->image->expects( 'get_attachment_id_from_settings' )
			->once()
			->with( 'person_logo' )
			->andReturn( $person_logo_id );
		$this->schema_image->expects( 'generate_from_attachment_id' )
			->once()
			->with( $person_schema_logo_id, $person_logo_id, $user_data->display_name )
			->andReturn( $image_schema );

		$this->expects_for_social_profiles( $this->social_profiles );

		$this->assertEquals( $expected, $this->instance->generate( $this->context ) );
	}

	/**
	 * Tests whether generate returns false when no user id could be determined.
	 *
	 * @covers ::__construct
	 * @covers ::generate
	 * @covers ::determine_user_id
	 */
	public function test_generate_no_user_id() {
		$this->context->site_user_id = 1337;

		$this->expects_for_determine_user_id( 'false' );

		$this->assertFalse( $this->instance->generate( $this->context ) );
	}

	/**
	 * Tests whether generate returns false when no user id 0 was determined.
	 *
	 * @covers ::__construct
	 * @covers ::generate
	 * @covers ::determine_user_id
	 */
	public function test_generate_user_id_zero() {
		$this->context->site_user_id = 1337;

		$this->expects_for_determine_user_id( 'zero' );

		$this->assertFalse( $this->instance->generate( $this->context ) );
	}

	/**
	 * Tests whether generate returns the expected schema without userdata.
	 *
	 * @covers ::__construct
	 * @covers ::generate
	 * @covers ::determine_user_id
	 * @covers ::build_person_data
	 */
	public function test_generate_without_userdata() {
		$this->context->site_user_id = 1337;

		$expected = [
			'@type' => [ 'Person', 'Organization' ],
			'@id'   => 'person_id',
		];

		$this->expects_for_determine_user_id();
		$this->expects_for_get_userdata( false );

		$this->assertEquals( $expected, $this->instance->generate( $this->context ) );
	}

	/**
	 * Tests whether generate returns the expected schema without a user description or social profiles.
	 *
	 * @covers ::__construct
	 * @covers ::generate
	 * @covers ::determine_user_id
	 * @covers ::build_person_data
	 * @covers ::add_image
	 * @covers ::set_image_from_options
	 * @covers ::set_image_from_avatar
	 * @covers ::get_social_profiles
	 */
	public function test_generate_without_user_description_or_social_profiles() {
		$this->context->site_user_id    = 1337;
		$this->context->site_url        = 'https://example.com/';
		$this->context->site_represents = false;

		$user_data = (object) [
			'display_name' => 'John',
			'description'  => '',
		];

		$expected = [
			'@type' => [ 'Person', 'Organization' ],
			'@id'   => 'person_id',
			'name'  => 'John',
			'logo'  => [ '@id' => 'https://example.com/#personlogo' ],
		];

		$this->expects_for_determine_user_id();
		$this->expects_for_get_userdata( $user_data );
		$this->expects_for_social_profiles( [] );

		$this->assertEquals( $expected, $this->instance->generate( $this->context ) );
	}

	/**
	 * Tests whether generate returns the expected schema with an image from an avatar.
	 *
	 * @covers ::__construct
	 * @covers ::generate
	 * @covers ::determine_user_id
	 * @covers ::build_person_data
	 * @covers ::add_image
	 * @covers ::set_image_from_avatar
	 * @covers ::get_social_profiles
	 */
	public function test_generate_image_from_avatar() {
		$this->context->site_user_id    = 1337;
		$this->context->site_url        = 'https://example.com/';
		$this->context->site_represents = false;

		$user_data = (object) [
			'display_name' => 'John Doe',
			'description'  => '',
			'user_email'   => 'johndoe@example.com',
		];

		$this->expects_for_determine_user_id();
		$this->expects_for_get_userdata( $user_data );
		$image_schema = $this->expects_for_set_image_from_avatar( $user_data );
		$this->expects_for_social_profiles( [] );

		$expected = [
			'@type' => [ 'Person', 'Organization' ],
			'@id'   => 'person_id',
			'name'  => $user_data->display_name,
			'logo'  => [ '@id' => 'https://example.com/#personlogo' ],
			'image' => $image_schema,
		];

		$this->assertEquals( $expected, $this->instance->generate( $this->context ) );
	}

	/**
	 * Tests whether generate returns the expected schema with an invalid avatar url.
	 *
	 * @covers ::__construct
	 * @covers ::generate
	 * @covers ::determine_user_id
	 * @covers ::build_person_data
	 * @covers ::add_image
	 * @covers ::set_image_from_avatar
	 * @covers ::get_social_profiles
	 */
	public function test_generate_invalid_avatar_url() {
		$this->context->site_user_id    = 1337;
		$this->context->site_url        = 'https://example.com/';
		$this->context->site_represents = false;

		$user_data = (object) [
			'display_name' => 'John Doe',
			'description'  => '',
			'user_email'   => 'johndoe@example.com',
		];

		$expected = [
			'@type' => [ 'Person', 'Organization' ],
			'@id'   => 'person_id',
			'name'  => 'John Doe',
			'logo'  => [ '@id' => 'https://example.com/#personlogo' ],
		];

		$this->expects_for_determine_user_id();
		$this->expects_for_get_userdata( $user_data );
		$this->expects_for_set_image_from_avatar( $user_data, 'empty_avatar_url' );
		$this->expects_for_social_profiles( [] );

		$this->assertEquals( $expected, $this->instance->generate( $this->context ) );
	}

	/**
	 * Tests whether generate returns the expected schema when social profiles are not an array.
	 *
	 * @covers ::__construct
	 * @covers ::generate
	 * @covers ::determine_user_id
	 * @covers ::build_person_data
	 * @covers ::add_image
	 * @covers ::set_image_from_options
	 * @covers ::set_image_from_avatar
	 * @covers ::get_social_profiles
	 */
	public function test_generate_social_profiles_non_array() {
		$this->context->site_user_id    = 1337;
		$this->context->site_url        = 'https://example.com/';
		$this->context->site_represents = false;

		$user_data = (object) [
			'display_name' => 'John Doe',
			'description'  => '',
		];

		$expected = [
			'@type' => [ 'Person', 'Organization' ],
			'@id'   => 'person_id',
			'name'  => 'John Doe',
			'logo'  => [ '@id' => 'https://example.com/#personlogo' ],
		];

		$this->expects_for_determine_user_id();
		$this->expects_for_get_userdata( $user_data );
		$this->expects_for_social_profiles( 'this is not an array' );

		$this->assertEquals( $expected, $this->instance->generate( $this->context ) );
	}

	/**
	 * Tests whether generate returns the expected schema when social profiles contain non string or falsy values.
	 *
	 * @covers ::__construct
	 * @covers ::generate
	 * @covers ::determine_user_id
	 * @covers ::build_person_data
	 * @covers ::add_image
	 * @covers ::set_image_from_options
	 * @covers ::set_image_from_avatar
	 * @covers ::get_social_profiles
	 * @covers ::url_social_site
	 */
	public function test_generate_social_profiles_non_string_or_falsy_values() {
		$this->context->site_user_id    = 1337;
		$this->context->site_url        = 'https://example.com/';
		$this->context->site_represents = false;

		$user_data = (object) [
			'display_name' => 'John Doe',
			'description'  => '',
		];

		$expected = [
			'@type' => [ 'Person', 'Organization' ],
			'@id'   => 'person_id',
			'name'  => 'John Doe',
			'logo'  => [ '@id' => 'https://example.com/#personlogo' ],
			'sameAs' => [
				'https://example.com/social/facebook',
				'https://example.com/social/wiki',
			],
		];

		$this->expects_for_determine_user_id();
		$this->expects_for_get_userdata( $user_data );
		$this->expects_for_social_profiles( [
			'facebook'  => 'facebook',
			'instagram' => 1234,
			'youtube'   => false,
			'wikipedia' => 'wiki',
		] );

		$this->assertEquals( $expected, $this->instance->generate( $this->context ) );
	}

	/**
	 * Tests whether the person Schema piece is shown when the site represents a person.
	 *
	 * @covers ::__construct
	 * @covers ::is_needed
	 */
	public function test_is_shown_when_site_represents_person() {
		$this->context->site_represents = 'person';

		$this->assertTrue( $this->instance->is_needed( $this->context ) );
	}

	/**
	 * Tests whether the person Schema piece is shown on author archive pages.
	 *
	 * @covers ::__construct
	 * @covers ::is_needed
	 */
	public function test_is_shown_on_author_archive_pages() {
		$this->context->indexable = (Object) [
			'object_type' => 'user',
		];

		$this->assertTrue( $this->instance->is_needed( $this->context ) );
	}

	/**
	 * Tests is not needed.
	 *
	 * @covers ::__construct
	 * @covers ::is_needed
	 */
	public function test_is_not_needed() {
		$this->context->site_represents        = 'organization';
		$this->context->indexable->object_type = 'post';

		$this->assertFalse( $this->instance->is_needed( $this->context ) );
	}

	/**
	 * Sets the tests for determine_user_id.
	 *
	 * @param string $scenario The scenario to set.
	 */
	protected function expects_for_determine_user_id( $scenario = 'default' ) {
		$user_id = $this->context->site_user_id;

		switch ( $scenario ) {
			case 'false':
				$user_id = false;
				break;
			case 'zero':
				$user_id = 0;
				break;
		}

		Filters\expectApplied( 'wpseo_schema_person_user_id' )
			->once()
			->with( $this->context->site_user_id )
			->andReturn( $user_id );
	}

	/**
	 * Sets the tests for get_userdata inside build_person_data.
	 *
	 * @param object|false $user_data The user data get_userdata returns. An object representing WP_User or false.
	 */
	protected function expects_for_get_userdata( $user_data ) {
		Functions\expect( 'get_userdata' )
			->once()
			->with( $this->context->site_user_id )
			->andReturn( $user_data );
		$this->id->expects( 'get_user_schema_id' )
			->once()
			->with( $this->context->site_user_id, $this->context )
			->andReturn( 'person_id' );

		// No more tests needed when there is no user data.
		if ( $user_data === false ) {
			return;
		}

		$this->html->expects( 'smart_strip_tags' )
			->once()
			->with( $user_data->display_name )
			->andReturn( $user_data->display_name );

		if ( empty( $user_data->description ) ) {
			$this->html->expects( 'smart_strip_tags' )
				->never()
				->with( $user_data->description );
			return;
		}

		$this->html->expects( 'smart_strip_tags' )
			->once()
			->with( $user_data->description )
			->andReturn( $user_data->description );
	}

	/**
	 * Sets the tests for get_social_profiles.
	 *
	 * @param string[] $social_profiles The social profiles after the `wpseo_schema_person_social_profiles` filter.
	 */
	protected function expects_for_social_profiles( $social_profiles ) {
		Filters\expectApplied( 'wpseo_schema_person_social_profiles' )
			->once()
			->with( $this->social_profiles, $this->context->site_user_id )
			->andReturn( $social_profiles );

		if ( empty( $social_profiles ) || ! \is_array( $social_profiles ) ) {
			Functions\expect( 'get_the_author_meta' )
				->never();

			return;
		}

		// Tests for the method `url_social_site`.
		foreach ( $social_profiles as $social_profile ) {
			if ( ! \is_string( $social_profile ) ) {
				Functions\expect( 'get_the_author_meta' )
					->never()
					->with( $social_profile, $this->context->site_user_id );

				continue;
			}

			Functions\expect( 'get_the_author_meta' )
				->once()
				->with( $social_profile, $this->context->site_user_id )
				->andReturn( 'https://example.com/social/' . $social_profile );
		}
	}

	/**
	 * Sets the tests for set_image_from_avatar.
	 *
	 * @param object $user_data An object representing WP_User. Expected to have `display_name` and `user_email`.
	 * @param string $scenario  The scenario to test.
	 *
	 * @return array The image schema.
	 */
	protected function expects_for_set_image_from_avatar( $user_data, $scenario = 'default' ) {
		$image_schema = [
			'@type'      => 'ImageObject',
			'@id'        => $this->context->site_url . $this->id->person_logo_hash,
			'inLanguage' => 'en-US',
			'url'        => 'https://example.com/image.png',
			'width'      => 64,
			'height'     => 128,
			'caption'    => 'Person image',
		];
		$avatar_url = $image_schema['url'];

		switch ( $scenario ) {
			case 'empty_avatar_url':
				$avatar_url = '';
				break;
		}

		Functions\expect( 'get_option' )
			->once()
			->with( 'show_avatars' )
			->andReturn( true );
		Functions\expect( 'get_avatar_url' )
			->once()
			->with( $user_data->user_email )
			->andReturn( $avatar_url );

		// No more tests when the avatar url is empty.
		if ( empty( $avatar_url ) ) {
			$this->schema_image->expects( 'simple_image_object' )
				->never();

			return $image_schema;
		}

		$this->schema_image->expects( 'simple_image_object' )
			->once()
			->with( $image_schema['@id'], $avatar_url, $user_data->display_name )
			->andReturn( $image_schema );

		return $image_schema;
	}
}

<?php
/**
 * Contains Class TestOptionMigrationWs.
 *
 * @package WP-Auth0
 *
 * @since 3.9.0
 */

class OptionMigrationWsTest extends WP_Auth0_Test_Case {

	use AjaxHelpers;

	use DomDocumentHelpers;

	use TokenHelper;

	use UsersHelper;

	/**
	 * Instance of WP_Auth0_Admin.
	 *
	 * @var WP_Auth0_Admin
	 */
	public static $admin;

	/**
	 * Runs before each test starts.
	 */
	public function setUp(): void {
		parent::setUp();
		$router      = new WP_Auth0_Routes( self::$opts );
		self::$admin = new WP_Auth0_Admin( self::$opts, $router );
	}

	/**
	 * Test that the migration WS setting field is rendered properly.
	 */
	public function testThatSettingsFieldRendersProperly() {
		$field_args = [
			'label_for' => 'wpa0_migration_ws',
			'opt_name'  => 'migration_ws',
		];
		$router     = new WP_Auth0_Routes( self::$opts );
		$admin      = new WP_Auth0_Admin_Advanced( self::$opts, $router );

		// Get the field HTML.
		ob_start();
		$admin->render_migration_ws( $field_args );
		$field_html = ob_get_clean();

		$input = $this->getDomListFromTagName( $field_html, 'input' );
		$this->assertEquals( 1, $input->length );
		$this->assertEquals( $field_args['label_for'], $input->item( 0 )->getAttribute( 'id' ) );
		$this->assertEquals( 'checkbox', $input->item( 0 )->getAttribute( 'type' ) );
		$this->assertEquals(
			self::OPTIONS_NAME . '[' . $field_args['opt_name'] . ']',
			$input->item( 0 )->getAttribute( 'name' )
		);
	}

	/**
	 * Test that correct settings field documentation appears when the setting is off.
	 */
	public function testThatCorrectFieldDocsShowWhenMigrationIsOff() {
		$field_args = [
			'label_for' => 'wpa0_migration_ws',
			'opt_name'  => 'migration_ws',
		];
		$router     = new WP_Auth0_Routes( self::$opts );
		$admin      = new WP_Auth0_Admin_Advanced( self::$opts, $router );

		$this->assertFalse( self::$opts->get( $field_args['opt_name'] ) );

		// Get the field HTML.
		ob_start();
		$admin->render_migration_ws( $field_args );
		$field_html = ob_get_clean();

		$this->assertStringContainsString( 'User migration endpoints deactivated', $field_html );
		$this->assertStringContainsString( 'Custom database connections can be deactivated', $field_html );
		$this->assertStringContainsString( 'https://manage.auth0.com/#/connections/database', $field_html );
	}

	/**
	 * Test that correct settings field documentation and additional controls appear when the setting is on.
	 */
	public function testThatCorrectFieldDocsShowWhenMigrationIsOn() {
		$field_args = [
			'label_for' => 'wpa0_migration_ws',
			'opt_name'  => 'migration_ws',
		];

		self::$opts->set( $field_args['opt_name'], 1 );

		$router = new WP_Auth0_Routes( self::$opts );
		$admin  = new WP_Auth0_Admin_Advanced( self::$opts, $router );

		// Get the field HTML.
		ob_start();
		$admin->render_migration_ws( $field_args );
		$field_html = ob_get_clean();

		$this->assertStringContainsString( 'User migration endpoints activated', $field_html );
		$this->assertStringContainsString( 'The custom database scripts need to be configured manually', $field_html );
		$this->assertStringContainsString( 'https://auth0.com/docs/cms/wordpress/user-migration', $field_html );

		$code_block = $this->getDomListFromTagName( $field_html, 'code' );
		$this->assertEquals( 'code-block', $code_block->item( 0 )->getAttribute( 'class' ) );
		$this->assertEquals( 'auth0_migration_token', $code_block->item( 0 )->getAttribute( 'id' ) );
		$this->assertEquals( 'disabled', $code_block->item( 0 )->getAttribute( 'disabled' ) );
		$this->assertEquals( 'No migration token', $code_block->item( 0 )->nodeValue );

		$token_button = $this->getDomListFromTagName( $field_html, 'button' );
		$this->assertEquals( 'auth0_rotate_migration_token', $token_button->item( 0 )->getAttribute( 'id' ) );
		$this->assertEquals( 'Generate New Migration Token', trim( $token_button->item( 0 )->nodeValue ) );
		$this->assertStringContainsString(
			'This will change your migration token immediately',
			$token_button->item( 0 )->getAttribute( 'data-confirm-msg' )
		);
		$this->assertStringContainsString(
			'The new token must be changed in the custom scripts for your database Connection',
			$token_button->item( 0 )->getAttribute( 'data-confirm-msg' )
		);
	}

	/**
	 * Test that turning migration endpoints off does not affect new input.
	 */
	public function testThatChangingMigrationToOffKeepsTokenData() {
		self::$opts->set( 'migration_token', 'existing_token' );
		$validated = self::$admin->input_validator( [] );

		$this->assertArrayHasKey( 'migration_ws', $validated );
		$this->assertEmpty( $validated['migration_ws'] );
		$this->assertEquals( 'existing_token', $validated['migration_token'] );
	}

	/**
	 * Test that turning on migration keeps the existing token and sets an admin notification.
	 */
	public function testThatChangingMigrationToOnKeepsToken() {
		self::$opts->set( 'migration_token', 'new_token' );
		$input = [
			'migration_ws'  => '1',
			'client_secret' => '__test_client_secret__',
		];

		$validated = self::$admin->input_validator( $input );

		$this->assertEquals( 'new_token', $validated['migration_token'] );
		$this->assertEquals( $input['migration_ws'], $validated['migration_ws'] );
	}

	/**
	 * Test that turning on migration keeps the existing token and sets an admin notification.
	 */
	public function testThatChangingMigrationToOnKeepsWithJwtSetsId() {
		$client_secret   = '__test_client_secret__';
		$migration_token = self::makeHsToken( [ 'jti' => '__test_token_id__' ], $client_secret );
		self::$opts->set( 'migration_token', $migration_token );
		$input = [
			'migration_ws'  => '1',
			'client_secret' => $client_secret,
		];

		$validated = self::$admin->input_validator( $input );

		$this->assertEquals( $input['migration_ws'], $validated['migration_ws'] );
		$this->assertEquals( $migration_token, $validated['migration_token'] );
	}

	/**
	 * Test that turning on migration endpoints without a stored token will generate one.
	 */
	public function testThatChangingMigrationToOnGeneratesNewToken() {
		$input = [ 'migration_ws' => '1' ];

		$validated = self::$admin->input_validator( $input );

		$this->assertGreaterThan( 64, strlen( $validated['migration_token'] ) );
		$this->assertEquals( $input['migration_ws'], $validated['migration_ws'] );
	}

	/**
	 * Test that a migration token in a constant setting is picked up and validated.
	 *
	 * @runInSeparateProcess
	 */
	public function testThatMigrationTokenInConstantSettingIsValidated() {
		define( 'AUTH0_ENV_MIGRATION_TOKEN', '__test_constant_setting__' );
		self::$opts->set( 'migration_token', '__test_saved_setting__' );
		$input = [
			'migration_ws'  => '1',
			'client_secret' => '__test_client_secret__',
		];

		$opts   = new WP_Auth0_Options();
		$router = new WP_Auth0_Routes( $opts );
		$admin  = new WP_Auth0_Admin_Advanced( $opts, $router );

		$validated = $admin->migration_ws_validation( $input );

		$this->assertEquals( $input['migration_ws'], $validated['migration_ws'] );
		$this->assertEquals( AUTH0_ENV_MIGRATION_TOKEN, $validated['migration_token'] );
	}
}

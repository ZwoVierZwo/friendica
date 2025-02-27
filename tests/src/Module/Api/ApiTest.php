<?php
/**
 * @copyright Copyright (C) 2010-2022, the Friendica project
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 */

namespace Friendica\Test\src\Module\Api;

use Friendica\App;
use Friendica\Capabilities\ICanCreateResponses;
use Friendica\Core\Addon;
use Friendica\Core\Hook;
use Friendica\Database\Database;
use Friendica\DI;
use Friendica\Module\Special\HTTPException;
use Friendica\Security\Authentication;
use Friendica\Security\BasicAuth;
use Friendica\Test\FixtureTest;
use Friendica\Test\Util\AppDouble;
use Friendica\Test\Util\AuthenticationDouble;
use Friendica\Test\Util\AuthTestConfig;
use Mockery\MockInterface;
use Psr\Http\Message\ResponseInterface;

abstract class ApiTest extends FixtureTest
{
	// User data that the test database is populated with
	const SELF_USER = [
		'id'   => 42,
		'name' => 'Self contact',
		'nick' => 'selfcontact',
		'nurl' => 'http://localhost/profile/selfcontact'
	];

	const FRIEND_USER = [
		'id'   => 44,
		'name' => 'Friend contact',
		'nick' => 'friendcontact',
		'nurl' => 'http://localhost/profile/friendcontact'
	];

	const OTHER_USER = [
		'id'   => 43,
		'name' => 'othercontact',
		'nick' => 'othercontact',
		'nurl' => 'http://localhost/profile/othercontact'
	];

	/** @var HTTPException */
	protected $httpExceptionMock;

	// User ID that we know is not in the database
	const WRONG_USER_ID = 666;

	/**
	 * Assert that the string is XML and contain the root element.
	 *
	 * @param string $result       XML string
	 * @param string $root_element Root element name
	 *
	 * @return void
	 */
	protected function assertXml(string $result = '', string $root_element = '')
	{
		self::assertStringStartsWith('<?xml version="1.0"?>', $result);
		self::assertStringContainsString('<' . $root_element, $result);
		// We could probably do more checks here.
	}

	/**
	 * Assert that an user array contains expected keys.
	 *
	 * @param \stdClass $user User
	 *
	 * @return void
	 */
	protected function assertSelfUser(\stdClass $user)
	{
		self::assertEquals(self::SELF_USER['id'], $user->uid);
		self::assertEquals(self::SELF_USER['id'], $user->cid);
		self::assertEquals(1, $user->self);
		self::assertEquals('DFRN', $user->location);
		self::assertEquals(self::SELF_USER['name'], $user->name);
		self::assertEquals(self::SELF_USER['nick'], $user->screen_name);
		self::assertEquals('dfrn', $user->network);
		self::assertTrue($user->verified);
	}

	/**
	 * Assert that an user array contains expected keys.
	 *
	 * @param \stdClass $user User
	 *
	 * @return void
	 */
	protected function assertOtherUser(\stdClass $user)
	{
		self::assertEquals(self::OTHER_USER['id'], $user->id);
		self::assertEquals(self::OTHER_USER['id'], $user->id_str);
		self::assertEquals(self::OTHER_USER['name'], $user->name);
		self::assertEquals(self::OTHER_USER['nick'], $user->screen_name);
		self::assertFalse($user->verified);
	}

	/**
	 * Assert that a status array contains expected keys.
	 *
	 * @param \stdClass $status Status
	 *
	 * @return void
	 */
	protected function assertStatus(\stdClass $status)
	{
		self::assertIsString($status->text);
		self::assertIsInt($status->id);
		// We could probably do more checks here.
	}

	/**
	 * Get the path to a temporary empty PNG image.
	 *
	 * @return string Path
	 */
	protected function getTempImage()
	{
		$tmpFile = tempnam(sys_get_temp_dir(), 'tmp_file');
		file_put_contents(
			$tmpFile,
			base64_decode(
			// Empty 1x1 px PNG image
				'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8/5+hHgAHggJ/PchI7wAAAABJRU5ErkJggg=='
			)
		);

		return $tmpFile;
	}

	/**
	 * Transforms a response into a JSON class
	 *
	 * @param ResponseInterface $response
	 *
	 * @return mixed
	 */
	protected function toJson(ResponseInterface $response)
	{
		self::assertEquals(ICanCreateResponses::TYPE_JSON, $response->getHeaderLine(ICanCreateResponses::X_HEADER));

		$body = (string)$response->getBody();

		self::assertJson($body);

		return json_decode($body);
	}

	protected function setUp(): void
	{
		parent::setUp(); // TODO: Change the autogenerated stub

		$this->dice = $this->dice
			->addRule(Authentication::class, ['instanceOf' => AuthenticationDouble::class, 'shared' => true])
			->addRule(App::class, ['instanceOf' => AppDouble::class, 'shared' => true]);
		DI::init($this->dice);

		// Manual override to bypass API authentication
		DI::app()->setIsLoggedIn(true);

		$this->httpExceptionMock = $this->dice->create(HTTPException::class);

		AuthTestConfig::$authenticated = true;
		AuthTestConfig::$user_id       = 42;

		$this->installAuthTest();
	}

	protected function tearDown(): void
	{
		BasicAuth::setCurrentUserID();

		parent::tearDown(); // TODO: Change the autogenerated stub
	}

	/**
	 * installs auththest.
	 *
	 * @throws \Exception
	 */
	public function installAuthTest()
	{
		$addon           = 'authtest';
		$addon_file_path = __DIR__ . '/../../../Util/authtest/authtest.php';
		$t               = @filemtime($addon_file_path);

		@include_once($addon_file_path);
		if (function_exists($addon . '_install')) {
			$func = $addon . '_install';
			$func(DI::app());
		}

		/** @var Database $dba */
		$dba = $this->dice->create(Database::class);

		$dba->insert('addon', [
			'name'         => $addon,
			'installed'    => true,
			'timestamp'    => $t,
			'plugin_admin' => function_exists($addon . '_addon_admin'),
			'hidden'       => file_exists('addon/' . $addon . '/.hidden')
		]);

		Addon::loadAddons();
		Hook::loadHooks();
	}
}

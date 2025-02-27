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

namespace Friendica\Module;

use Friendica\App;
use Friendica\App\BaseURL;
use Friendica\BaseModule;
use Friendica\Content\Nav;
use Friendica\Core\Config\Capability\IManageConfigValues;
use Friendica\Core\L10n;
use Friendica\Core\Renderer;
use Friendica\DI;
use Friendica\Util\Profiler;
use Psr\Log\LoggerInterface;

/**
 * Shows the App menu
 */
class Apps extends BaseModule
{
	public function __construct(L10n $l10n, App\BaseURL $baseUrl, App\Arguments $args, LoggerInterface $logger, Profiler $profiler, Response $response, IManageConfigValues $config, array $server, array $parameters = [])
	{
		parent::__construct($l10n, $baseUrl, $args, $logger, $profiler, $response, $server, $parameters);

		$privateaddons = $config->get('config', 'private_addons');
		if ($privateaddons === "1" && !DI::userSession()->getLocalUserId()) {
			$baseUrl->redirect();
		}
	}

	protected function content(array $request = []): string
	{
		$apps = Nav::getAppMenu();

		if (count($apps) == 0) {
			DI::sysmsg()->addNotice($this->t('No installed applications.'));
		}

		$tpl = Renderer::getMarkupTemplate('apps.tpl');
		return Renderer::replaceMacros($tpl, [
			'$title' => $this->t('Applications'),
			'$apps'  => $apps,
		]);
	}
}

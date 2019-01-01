<?php

/*
 *
 * Newspaper
 *
 * Copyright © 2018-2019 Johnmacrocraft
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 */

namespace Johnmacrocraft\Newspaper\tasks;

use Johnmacrocraft\Newspaper\Newspaper;
use pocketmine\scheduler\Task;

class CheckSubscriptionsTask extends Task {

	/** @var Newspaper */
	private $plugin;

	public function __construct(Newspaper $plugin) {
		$this->plugin = $plugin;
	}

	public function onRun(int $currentTick) {
		$this->plugin->checkSubscriptions();
	}
}

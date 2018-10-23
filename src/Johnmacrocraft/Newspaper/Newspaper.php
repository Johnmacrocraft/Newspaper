<?php

/*
 *
 * Newspaper
 *
 * Copyright © 2018 Johnmacrocraft
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 */

namespace Johnmacrocraft\Newspaper;

use Johnmacrocraft\Newspaper\forms\MainForm;
use Johnmacrocraft\Newspaper\tasks\CheckSubscriptionsTask;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\item\ItemFactory;
use pocketmine\item\ItemIds;
use pocketmine\lang\BaseLang;
use pocketmine\Player;
use pocketmine\plugin\Plugin;
use pocketmine\plugin\PluginBase;
use pocketmine\Server;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use spoondetector\SpoonDetector;

class Newspaper extends PluginBase implements Listener {

	/** @var string */
	private $dataFolder;
	/** @var string */
	private $newspaperFolder;
	/** @var string */
	private $playersFolder;

	/** @var BaseLang */
	private $baseLang_eng;
	/** @var BaseLang */
	private $baseLang_kor;
	/** @var BaseLang */
	private $baseLang_jpn;

	public function onEnable() : void {
		SpoonDetector::printSpoon($this, "spoon.txt");

		$this->dataFolder = $this->getDataFolder();

		if(!is_dir($this->newspaperFolder = $this->dataFolder . "newspapers/")) {
			mkdir($this->newspaperFolder);
		}
		if(!is_dir($this->playersFolder = $this->dataFolder . "players/")) {
			mkdir($this->playersFolder);
		}
		$this->saveDefaultConfig();

		foreach($this->getLanguageList() as $lang) {
			$langName = pathinfo($lang, PATHINFO_FILENAME);
			$this->{"baseLang_$langName"} = new BaseLang($langName, $this->getFile() . "resources/lang/");
		}

		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->getScheduler()->scheduleRepeatingTask(new CheckSubscriptionsTask($this), 12000);
	}

	/**
	 * @param CommandSender $sender
	 * @param Command $command
	 * @param string $label
	 * @param string[] $args
	 *
	 * @return bool
	 */
	public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool {
		if($command->getName() === "newspaper") {
			if($sender instanceof Player) {
				$this->checkSubscriptions();
				$sender->sendForm(new MainForm($sender->getName()));
			} else {
				$sender->sendMessage(TextFormat::RED . $this->getLanguage($this->getConfig()->get("lang"))->translateString("command.onlyPlayers"));
			}
			return true;
		}
	}

	/**
	 * @param PlayerJoinEvent $event
	 * @priority MONITOR
	 */
	public function onJoin(PlayerJoinEvent $event) : void {
		if(!is_file($playerData = $this->getPlayersFolder() . ($name = strtolower($event->getPlayer()->getName())) . ".yml")) {
			new Config($playerData, Config::YAML, ["lang" => $this->getConfig()->get("lang"), "autorenew" => $this->getConfig()->get("autorenew"), "subscriptions" => []]);
		}

		$subscriptions = $this->getPlayerData($name);

		foreach($this->getSubscriptionsArray($subscriptions->getAll()) as $subscription) {
			$player = $event->getPlayer();
			foreach($queue = $subscriptions->getNested($key = "subscriptions." . $subscription . ".queue") as $newspaper) {
				$item = ItemFactory::fromString(ItemIds::WRITTEN_BOOK);
				$item->setCount(1);
				$item->setPages(($newspaperData = $this->getPublishedNewspaper($subscription, $newspaper))[1]->getAll());
				$item->setTitle($newspaperData[0]->get("name"));
				$item->setAuthor($newspaperData[0]->get("author"));
				$item->setGeneration($newspaperData[0]->get("generation"));

				if(!$player->getInventory()->canAddItem($item)) {
					$player->sendMessage(TextFormat::RED . $this->getLanguage(Newspaper::getPlayerData($name)->get("lang"))->translateString("main.error.sub.invNoSpace", [$subscription]));
					break 2;
				}

				$player->getInventory()->addItem($item);

				unset($queue[array_search($newspaper, $queue)]);
				$subscriptions->setNested($key, array_values($queue));
			}
		}
		$subscriptions->save();
	}

	/**
	 * Returns newspaper information.
	 *
	 * @param string $newspaper
	 *
	 * @return Config
	 */
	public function getNewspaperInfo(string $newspaper) : Config {
		return new Config($this->getNewspaperFolder() . strtolower($newspaper) . "/info.yml", Config::YAML);
	}

	/**
	 * Returns an array of path to all newspaper information files.
	 *
	 * @return array
	 */
	public function getAllNewspaperInfo() : array {
		return glob($this->getNewspaperFolder() . "*/info.yml");
	}

	/**
	 * Returns published newspaper for the given newspaper.
	 *
	 * @param string $newspaper
	 * @param string $published
	 *
	 * @return array
	*/
	public function getPublishedNewspaper(string $newspaper, string $published) : array {
		return [new Config(($path = $this->getNewspaperFolder() . strtolower($newspaper) . "/newspaper/" . $published) . ".yml", Config::YAML), new Config($path . ".dat", Config::SERIALIZED)];
	}

	/**
	 * Returns an array of path to all published newspapers.
	 *
	 * @param string $newspaper
	 *
	 * @return array
	 */
	public function getAllPublishedNewspapers(string $newspaper) : array {
		return glob($this->getNewspaperFolder() . strtolower($newspaper) . "/newspaper/*.yml");
	}

	/**
	 * Sets subscription status of specified newspaper for the given player.
	 *
	 * @param string $player
	 * @param string $newspaper
	 * @param \DateTime $subscribeUntil
	 */
	public function setSubscription(string $player, string $newspaper, ?\DateTime $subscribeUntil = null) : void {
		if($subscribeUntil === null) {
			$subscribeUntil = (new \DateTime())->add(new \DateInterval("P1M"));
		}

		$playerData = $this->getPlayerData($player);
		$playerData->setNested(($prefix = "subscriptions." . strtolower($newspaper)) . ".subscribeUntil", $subscribeUntil);
		$playerData->setNested($prefix . ".queue", []);
		$playerData->save();
	}

	/**
	 * Returns subscription status of specified newspaper for the given player.
	 *
	 * @param string $player
	 * @param string $newspaper
	 *
	 * @return array
	 */
	public function getSubscription(string $player, string $newspaper) : array {
		return $this->getPlayerData($player)->getNested("subscriptions." . $newspaper);
	}

	/**
	 * Removes subscription of specified newspaper for the given player.
	 *
	 * @param string $player
	 * @param string $newspaper
	 */
	public function removeSubscription(string $player, string $newspaper) : void {
		$playerData = $this->getPlayerData($player);
		$playerData->removeNested("subscriptions." . $newspaper);
		$playerData->save();
	}

	/**
	 * Renews subscription of specified newspaper for the given player.
	 *
	 * @param string $player
	 * @param string $newspaper
	 */
	public function renewSubscription(string $player, string $newspaper) : void {
		if($this->getPlayerData($player)->get("autorenew")) {
			if($this->canBuyNewspapers() && ($API = Newspaper::getPlugin()->getEconomyAPI())->reduceMoney($player, $this->getNewspaperInfo($newspaper)->getNested("price.subscriptions"), true, "Newspaper") === $API::RET_INVALID) {
				$this->removeSubscription($player, $newspaper);
				return;
			}

			$this->setSubscription($player, $newspaper);
			$this->getLogger()->notice("[Newspaper: Debug] Renewed subscription");
		} else {
			$this->removeSubscription($player, $newspaper);
		}
	}

	/**
	 * Checks subscriptions and performs actions.
	 *
	 * @param array|null $pathArray
	 */
	public function checkSubscriptions(?array $pathArray = null) {
		if($pathArray === null) {
			$pathArray = glob($this->getPlayersFolder() . "*.yml");
		}

		foreach($pathArray as $dataPath) {
			$playerData = new Config($dataPath, Config::YAML);

			foreach($this->getSubscriptionsArray($playerData->getAll()) as $subscription) {
				if(new \DateTime($playerData->getNested("subscriptions." . $subscription . ".subscribeUntil")) < new \DateTime()) {
					$this->renewSubscription(pathinfo($dataPath, PATHINFO_FILENAME), $subscription);
				}
			}
		}
	}

	/**
	 * Returns player data for the given player.
	 *
	 * @param string $player
	 *
	 * @return Config
	 */
	public function getPlayerData(string $player) : Config {
		return new Config($this->getPlayersFolder() . strtolower($player) . ".yml", Config::YAML);
	}

	/**
	 * Returns an array of subscriptions.
	 *
	 * @param $array
	 * @return array
	 */
	public function getSubscriptionsArray(array $array) : array {
		$result = [];
		unset($array["lang"]);
		unset($array["autorenew"]);
		foreach($array as $sub) {
			$result = array_merge($result, $sub);
		}
		return array_keys($result);
	}

	/**
	 * Returns api of Economy plugin.
	 *
	 * @return Plugin|null
	 */
	public function getEconomyAPI() : Plugin {
		return Server::getInstance()->getPluginManager()->getPlugin("EconomyAPI");
	}

	/**
	 * Returns whether the players can buy newspapers (checks if Economy plugin exists).
	 *
	 * @return bool
	 */
	public function canBuyNewspapers() : bool {
		return $this->getEconomyAPI() !== null && $this->getEconomyAPI()->isEnabled();
	}

	/**
	 * Returns whether the given player has the permission, and sends message if true.
	 *
	 * @param Player $player
	 * @param string $perm
	 * @param string $action
	 *
	 * @return bool
	 */
	public function badPerm(Player $player, string $perm, string $action = "main.perm.generic") : bool {
		if(!$player->hasPermission("newspaper." . $perm)) {
			$player->sendMessage(TextFormat::RED . $this->getLanguage($this->getPlayerData($player->getName())->get("lang"))->translateString("main.perm.base", ["%$action"]));
			return true;
		}
		return false;
	}

	/**
	 * Returns this class.
	 *
	 * @return Newspaper
	 */
	public static function getPlugin() : Newspaper {
		return Server::getInstance()->getPluginManager()->getPlugin("Newspaper");
	}

	/**
	 * Returns path to newspaper folder.
	 *
	 * @return string
	 */
	public function getNewspaperFolder() : string {
		return $this->newspaperFolder;
	}

	/**
	 * Returns path to players folder.
	 *
	 * @return string
	 */
	public function getPlayersFolder() : string {
		return $this->playersFolder;
	}

	/**
	 * Returns path to language files folder.
	 *
	 * @return string
	 */
	public function getLanguageFolder() : string {
		return $this->getFile() . "resources/lang/";
	}

	/**
	 * Returns BaseLang for the given language.
	 *
	 * @param string $lang
	 *
	 * @return BaseLang
	 */
	public function getLanguage(string $lang) : BaseLang {
		return $this->{"baseLang_$lang"};
	}

	/**
	 * Returns an array of path to all language files.
	 *
	 * @return array
	 */
	public function getLanguageList() : array {
		$langList = []; //From PocketMine
		if(is_dir($this->getLanguageFolder())) {
			foreach(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->getFile() . "resources/lang/")) as $langPath) {
				if($langPath->isFile()) {
					$path = str_replace(DIRECTORY_SEPARATOR, "/", substr((string) $langPath, strlen($this->getFile() . "resources/lang/")));
					$langList[$path] = $langPath;
				}
			}
		}

		return $langList;
	}
}
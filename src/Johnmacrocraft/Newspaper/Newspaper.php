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

namespace Johnmacrocraft\Newspaper;

use Johnmacrocraft\Newspaper\forms\MainForm;
use Johnmacrocraft\Newspaper\tasks\CheckSubscriptionsTask;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\item\WrittenBook;
use pocketmine\lang\BaseLang;
use pocketmine\Player;
use pocketmine\plugin\Plugin;
use pocketmine\plugin\PluginBase;
use pocketmine\Server;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use spoondetector\SpoonDetector;

class Newspaper extends PluginBase implements Listener {

	/** @var Newspaper */
	private static $instance;

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
		self::$instance = $this;

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
		$this->getScheduler()->scheduleRepeatingTask(new CheckSubscriptionsTask($this), 12000); //10 minutes
	}

	/**
	 * @param CommandSender $sender
	 * @param Command $command
	 * @param string $label
	 * @param string[] $args
	 *
	 * @return bool
	 * @throws \Exception
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
	public function onPlayerJoin(PlayerJoinEvent $event) : void {
		$playerName = $event->getPlayer()->getLowerCaseName();
		if(!is_file($playerData = $this->getPlayersFolder() . "$playerName.yml")) {
			new Config($playerData, Config::YAML, [
				"lang" => $this->getConfig()->get("lang"),
				"autorenew" => $this->getConfig()->get("autorenew"),
				"subscriptions" => []
			]);
		}

		$subscriptions = $this->getPlayerData($playerName);

		foreach($this->getSubscriptionsArray($subscriptions->getAll()) as $subscription) {
			$player = $event->getPlayer();
			$key = "subscriptions.$subscription.queue";
			foreach($queue = $subscriptions->getNested($key) as $newspaper) {
				$newspaperInfo = $this->getPublishedInfo($subscription, $newspaper);
				$newspaperPages = $this->getPublishedPages($subscription, $newspaper);
				$item = new WrittenBook;
				$item->setCount(1);
				$item->setPages($newspaperInfo->getAll());
				$item->setTitle($newspaperPages->get("name"));
				$item->setAuthor($newspaperInfo->get("author"));
				$item->setGeneration($newspaperPages->get("generation"));

				if(!$player->getInventory()->canAddItem($item)) {
					$player->sendMessage(TextFormat::RED . $this->getLanguage($this->getPlayerData($playerName)->get("lang"))->translateString("main.error.sub.invNoSpace", [$subscription]));
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
	 * Creates a new newspaper.
	 *
	 * @param string $newspaper
	 * @param string $description
	 * @param array $member
	 * @param string $icon
	 * @param int $perOneFee
	 * @param int $subsFee
	 */
	public function createNewspaper(string $newspaper, string $description, array $member, string $icon, int $perOneFee, int $subsFee) : void {
		$newspaperPath = $this->getNewspaperFolder() . strtolower($newspaper);

		mkdir($newspaperPath);
		mkdir($newspaperPath . "/newspaper");

		$info = new Config($newspaperPath . "/info.yml", Config::YAML, [
			"name" => $newspaper,
			"description" => $description,
			"member" => array_map("strtolower", $member),
			"icon" => $icon
		]);

		$info->setNested("price.perOne", $perOneFee);
		$info->setNested("price.subscriptions", $subsFee);
		$info->set("profit", 0);
		$info->save();
	}

	/**
	 * Publishes a newspaper.
	 *
	 * @param string $mainNewspaper
	 * @param string $newspaper
	 * @param string $description
	 * @param string $author
	 * @param int $generation
	 * @param array $contents
	 * @param bool|null $checkExpired
	 *
	 * @throws \Exception
	 */
	public function publishNewspaper(string $mainNewspaper, string $newspaper, string $description, string $author, int $generation, array $contents, ?bool $checkExpired = true) : void {
		$basePath = $this->getNewspaperFolder() . "$mainNewspaper/newspaper/" . strtolower($newspaper);

		$newspaperInfo = new Config("$basePath.yml", Config::YAML, [
			"name" => $newspaper,
			"description" => $description,
			"author" => $author,
			"generation" => $generation
		]);
		$newspaperData = new Config("$basePath.dat", Config::SERIALIZED, $contents);

		if($checkExpired) {
			$this->checkSubscriptions();
		}
		foreach(glob($this->getPlayersFolder() . "*.yml") as $playerDataPath) {
			$playerData = new Config($playerDataPath, Config::YAML);

			if(isset($playerData->getAll()["subscriptions"][$mainNewspaper])) {
				if(($subscriber = $this->getServer()->getPlayer($subscriberName = pathinfo($playerDataPath, PATHINFO_FILENAME)))->isOnline()) {
					$item = new WrittenBook;
					$item->setCount(1);
					$item->setPages(($newspaperData->getAll()));
					$item->setTitle($newspaperInfo->get("name"));
					$item->setAuthor($newspaperInfo->get("author"));
					$item->setGeneration($newspaperInfo->get("generation"));

					if($subscriber->getInventory()->canAddItem($item)) {
						$subscriber->getInventory()->addItem($item);
						break;
					} else {
						$subscriber->sendMessage(TextFormat::GOLD . $this->getLanguage($this->getPlayerData($subscriberName)->get("lang"))->translateString("gui.publish.sub.info"));
					}

				}
				$key = "subscriptions.$mainNewspaper.queue";
				$queue = $playerData->getNested($key);
				$queue[] = strtolower($newspaper);
				$playerData->setNested($key, $queue);
				$playerData->save();
			}
		}
	}

	/**
	 * Returns newspaper information.
	 *
	 * @param string $newspaper
	 *
	 * @return Config
	 */
	public function getNewspaperInfo(string $newspaper) : Config {
		if(!file_exists($path = $this->getNewspaperFolder() . strtolower($newspaper) . "/info.yml")) {
			throw new \RuntimeException("Newspaper not found");
		}
		return new Config($path, Config::YAML);
	}

	/**
	 * Returns an array of path to all the newspaper information files.
	 *
	 * @return array
	 */
	public function getAllNewspaperInfo() : array {
		return glob($this->getNewspaperFolder() . "*/info.yml");
	}

	/**
	 * Returns the published newspaper info for the given newspaper.
	 *
	 * @param string $newspaper
	 * @param string $published
	 *
	 * @return Config
	*/
	public function getPublishedInfo(string $newspaper, string $published) : Config {
		if(!file_exists($path = $this->getNewspaperFolder() . strtolower($newspaper) . "/newspaper/" . $published . ".yml")) {
			throw new \RuntimeException("Published newspaper info not found");
		}
		return new Config($path, Config::YAML);
	}

	/**
	 * Returns the published newspaper pages for the given newspaper.
	 *
	 * @param string $newspaper
	 * @param string $published
	 *
	 * @return Config
	 */
	public function getPublishedPages(string $newspaper, string $published) : Config {
		if(!file_exists($path = $this->getNewspaperFolder() . strtolower($newspaper) . "/newspaper/" . $published . ".dat")) {
			throw new \RuntimeException("Published newspaper pages not found");
		}
		return new Config($path, Config::SERIALIZED);
	}

	/**
	 * Returns an array of path to all the published newspapers for the given newspaper.
	 *
	 * @param string $newspaper
	 *
	 * @return array
	 */
	public function getAllPublished(string $newspaper) : array {
		if(!file_exists($this->getNewspaperFolder() . strtolower($newspaper))) {
			throw new \RuntimeException("Newspaper not found");
		}
		$escapedName = str_replace("[", "\[", $newspaper); //First checks for brackets
		$escapedName = str_replace("]", "\]", $escapedName);
		$escapedName = str_replace("\[", "[[]", $escapedName); //Second checks for brackets
		$escapedName = str_replace("\]", "[]]", $escapedName);
		return glob($this->getNewspaperFolder() . strtolower($escapedName) . "/newspaper/*.yml");
	}

	/**
	 * Sets the subscription status of the specified newspaper for the given player.
	 *
	 * @param string $player
	 * @param string $newspaper
	 * @param \DateTime $subscribeUntil
	 *
	 * @throws \Exception
	 */
	public function setSubscription(string $player, string $newspaper, ?\DateTime $subscribeUntil = null) : void {
		if($subscribeUntil === null) {
			$subscribeUntil = (new \DateTime())->add(new \DateInterval("P1M"));
		}

		$playerData = $this->getPlayerData($player);
		$prefix = "subscriptions." . strtolower($newspaper);
		$playerData->setNested("$prefix.subscribeUntil", $subscribeUntil);
		$playerData->setNested("$prefix.queue", []);
		$playerData->save();
	}

	/**
	 * Returns the subscription status of the specified newspaper for the given player.
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
	 * Removes the subscription of the specified newspaper for the given player.
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
	 * Renews the subscription of the specified newspaper for the given player.
	 *
	 * @param string $player
	 * @param string $newspaper
	 *
	 * @throws \Exception
	 */
	public function renewSubscription(string $player, string $newspaper) : void {
		if($this->getPlayerData($player)->get("autorenew")) {
			if($this->canBuyNewspapers() && ($API = $this->getEconomyAPI())->reduceMoney($player, $this->getNewspaperInfo($newspaper)->getNested("price.subscriptions"), true, "Newspaper") === $API::RET_INVALID) {
				$this->removeSubscription($player, $newspaper);
				return;
			}

			$this->setSubscription($player, $newspaper);
		} else {
			$this->removeSubscription($player, $newspaper);
		}
	}

	/**
	 * Checks subscriptions and performs actions.
	 *
	 * @param array|null $pathArray
	 *
	 * @throws \Exception
	 */
	public function checkSubscriptions(?array $pathArray = null) : void {
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
	 * Returns the player data for the given player.
	 *
	 * @param string $player
	 *
	 * @return Config
	 */
	public function getPlayerData(string $player) : Config {
		if(!file_exists($path = $this->getPlayersFolder() . strtolower($player) . ".yml")) {
			throw new \RuntimeException("Player data not found");
		}
		return new Config($path, Config::YAML);
	}

	/**
	 * Returns an array of subscriptions.
	 *
	 * @param $array
	 * @return array
	 */
	public function getSubscriptionsArray(array $array) : array {
		$result = [];
		unset($array["lang"]); //Remove other values that have nothing to do with subscriptions first
		unset($array["autorenew"]);
		foreach($array as $sub) {
			$result = array_merge($result, $sub);
		}
		return array_keys($result);
	}

	/**
	 * Returns the API of Economy plugin.
	 *
	 * @return Plugin|null
	 */
	public function getEconomyAPI() : ?Plugin {
		return $this->getServer()->getPluginManager()->getPlugin("EconomyAPI");
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
	 * Returns whether the given player has the permission, and sends the message if true.
	 *
	 * @param Player $player
	 * @param string $perm
	 * @param string $action
	 *
	 * @return bool
	 */
	public function badPerm(Player $player, string $perm, string $action = "main.perm.generic") : bool {
		if(!$player->hasPermission("newspaper.$perm")) {
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
		return self::$instance;
	}

	/**
	 * Returns the path to the newspaper folder.
	 *
	 * @return string
	 */
	public function getNewspaperFolder() : string {
		return $this->newspaperFolder;
	}

	/**
	 * Returns the path to the players folder.
	 *
	 * @return string
	 */
	public function getPlayersFolder() : string {
		return $this->playersFolder;
	}

	/**
	 * Returns the path to the language files folder.
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
	 * Returns an array of path to all the language files.
	 *
	 * @return array
	 */
	public function getLanguageList() : array {
		$langList = []; //From PocketMine
		if(is_dir($this->getLanguageFolder())) {
			foreach(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->getLanguageFolder())) as $langPath) {
				if($langPath->isFile()) {
					$path = str_replace(DIRECTORY_SEPARATOR, "/", substr((string) $langPath, strlen($this->getLanguageFolder())));
					$langList[$path] = $langPath;
				}
			}
		}

		return $langList;
	}
}

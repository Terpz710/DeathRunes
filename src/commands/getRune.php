<?php
declare(strict_types=1);

namespace PrograMistV1\DeathRunes\commands;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\utils\InvalidCommandSyntaxException;
use pocketmine\permission\DefaultPermissionNames;
use pocketmine\permission\DefaultPermissions;
use pocketmine\permission\Permission;
use pocketmine\permission\PermissionManager;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use PrograMistV1\DeathRunes\DeathRunes;

class getRune extends Command{
    public function __construct(){
        $op = PermissionManager::getInstance()->getPermission(DefaultPermissionNames::GROUP_OPERATOR);
        DefaultPermissions::registerPermission(new Permission("command.getrune"), [$op]);
        $this->setPermission("command.getrune");
        parent::__construct("getrune", "allows you to get a rune into your inventory", "/getrune <runeID=list> [count: int]");
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args) : void{
        if(!$sender instanceof Player){
            return;
        }
        if(count($args) > 2){
            throw new InvalidCommandSyntaxException();
        }
        $runes = DeathRunes::getRunes();
        $list = array_keys($runes);
        if(count($args) == 0){
            $sender->sendMessage("Rune list: ".TextFormat::RESET.implode(", ", $list));
            return;
        }
        $rune = strtolower($args[0]);
        if(!in_array($rune, $list)){
            $sender->sendMessage(TextFormat::RED."Unknown rune name ".$args[0]."!\n"."Available runes: ".TextFormat::RESET.implode(", ", $list));
            return;
        }
        $item = DeathRunes::getRune($rune);
        $item->setCount(intval($args[1] ?? 1));
        $sender->getInventory()->addItem($item);
        $sender->sendMessage(TextFormat::GREEN."You have successfully obtained the ".TextFormat::YELLOW.$item->getCustomName());
    }
}
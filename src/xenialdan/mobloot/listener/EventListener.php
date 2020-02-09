<?php

namespace xenialdan\mobloot\listener;

use InvalidArgumentException;
use pocketmine\entity\Creature;
use pocketmine\event\entity\EntityDeathEvent;
use pocketmine\event\Listener;
use pocketmine\network\mcpe\protocol\AddActorPacket;
use pocketmine\plugin\Plugin;
use pocketmine\plugin\PluginException;
use xenialdan\mobloot\LootGenerator;

class EventListener implements Listener
{
    /** @var Plugin|null */
    private static $registrant;

    public static function isRegistered(): bool
    {
        return self::$registrant instanceof Plugin;
    }

    public static function getRegistrant(): Plugin
    {
        return self::$registrant;
    }

    public static function unregister(): void
    {
        self::$registrant = null;
    }

    /**
     * @param Plugin $plugin
     * @throws PluginException
     */
    public static function register(Plugin $plugin): void
    {
        if (self::isRegistered()) {
            return;//silent return
        }

        self::$registrant = $plugin;
        $plugin->getServer()->getPluginManager()->registerEvents(new self, $plugin);
    }

    public function onDeath(EntityDeathEvent $ev):void
    {
        /** @var Creature $entity */
        if (($entity = $ev->getEntity()) instanceof Creature) {
            $saveName = AddActorPacket::LEGACY_ID_MAP_BC[$entity::NETWORK_ID] ?? "";
            if (empty($saveName)) return;
            try {
                $loot = new LootGenerator("loot_tables/entities/" . str_replace("minecraft:", "", $saveName) . ".json", $entity);
                $ev->setDrops($loot->getRandomLoot());
            } catch (InvalidArgumentException $exception) {
                self::getRegistrant()->getLogger()->logException($exception);
            }
        }
    }
}
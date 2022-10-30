<?php

namespace Nyrok\TotemOfFertility;

use JsonException;
use pocketmine\entity\Location;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerEntityInteractEvent;
use pocketmine\item\VanillaItems;
use pocketmine\nbt\tag\CompoundTag;

final class EventListener implements Listener
{
    /**
     * @throws JsonException
     */
    public function onPlace(BlockPlaceEvent $event)
    {
        if (($item = $event->getPlayer()->getInventory()->getItemInHand())->getId() === Main::$blockId) {
            if ($event->isCancelled()) return;
            $pos = $event->getBlock()->getPosition();
            $location = new Location($pos->x, $pos->y, $pos->z, $event->getPlayer()->getWorld(), 1, 1);
            $location->x = (int)($location->x) + .5;
            $location->y = (int)($location->y);
            $location->z = (int)($location->z) + .5;
            $entity = new Totem($location, CompoundTag::create());
            $entity->spawnToAll();
            $event->cancel();
            if ($item->getCount() - 1 > 0) $event->getPlayer()->getInventory()->setItemInHand($item->setCount($item->getCount() - 1));
            else $event->getPlayer()->getInventory()->setItemInHand(VanillaItems::AIR());
        }
    }

    public function onInteract(PlayerEntityInteractEvent $event)
    {
        if (($item = $event->getPlayer()->getInventory()->getItemInHand())->getId() === Main::$fuelItemId and ($entity = $event->getEntity()) instanceof Totem) {
            $entity->addFuel(1);
            if ($item->getCount() - 1 <= 0) $event->getPlayer()->getInventory()->setItemInHand(VanillaItems::AIR());
            else $event->getPlayer()->getInventory()->setItemInHand($item->setCount($item->getCount() - 1));
        }
    }

}
<?php

namespace Nyrok\TotemOfFertility;

use JsonException;
use pocketmine\block\Air;
use pocketmine\block\Crops;
use pocketmine\block\NetherWartPlant;
use pocketmine\block\Sugarcane;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\Human;
use pocketmine\entity\Location;
use pocketmine\entity\Skin;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ShortTag;
use pocketmine\Server;
use pocketmine\utils\Random;
use pocketmine\world\particle\HeartParticle;
use pocketmine\world\Position;

final class Totem extends Human
{
    private int $fuel;
    protected $immobile = true;
    protected $silent = true;
    private int $baseTick;

    /**
     * @throws JsonException
     */
    public function __construct(Location $location, CompoundTag $nbt)
    {
        $this->setSkin(new Skin(
            "totem",
            Main::getInstance()->PNGtoBYTES(Main::getInstance()->getDataFolder() . Main::$texture),
            "",
            Main::$identifier,
            file_get_contents(Main::getInstance()->getDataFolder() . Main::$geometry)
        ));
        $this->fuel = $nbt->getTag("palafuel")?->getValue() ?? 0;
        $this->baseTick = Server::getInstance()->getTick();
        parent::__construct($location, $this->getSkin(), $nbt->getTag("palafuel") ? $nbt : $nbt->setTag('palafuel', new ShortTag($this->fuel)));
        $this->setMaxHealth(Main::$health);
        $this->setHealth(Main::$health);
        $this->setImmobile(true);
    }

    protected function entityBaseTick(int $tickDiff = 1): bool
    {
        $this->setNameTag(Main::$nametag);
        $this->setScoreTag(str_replace("{fuel}", $this->fuel, Main::$scoretag) . "\n§a" . $this->getHealth() . "/" . $this->getMaxHealth() . " PV");
        return parent::entityBaseTick($tickDiff);
    }

    public function onUpdate(int $currentTick): bool
    {
        if ((Server::getInstance()->getTick() - $this->baseTick) >= (60 * 20 * Main::$growthTime)) {
            $this->baseTick = Server::getInstance()->getTick();
            $this->growth();
        }
        return parent::onUpdate($currentTick);
    }

    public function setFuel(int $count): void
    {
        $this->fuel = $count;
    }

    public function addFuel(int $count): void
    {
        $this->fuel += $count;
        $this->sendParticle("happyvillager");
    }

    public function removeFuel(int $count): void
    {
        $this->fuel -= $count;
        $this->sendParticle("angryvillager", 0.1, 3, 0.1, 1);
    }

    protected function onDeath(): void
    {
        $this->flagForDespawn();
        $this->getWorld()->dropItem($this->getLocation(), VanillaBlocks::GOLD()->asItem());
    }

    public function getFuel(): int
    {
        return $this->saveNBT()->getTag('palafuel')?->getValue() ?? $this->fuel ?? 0;
    }

    public function saveNBT(): CompoundTag
    {
        return parent::saveNBT()->setTag("palafuel", new ShortTag($this->fuel));
    }

    private function sendParticle(string $particle, float $xd = 0.5, float $yd = 3.0, float $zd = 0.5, int $count = 30)
    {
        $name = strtolower($particle);
        $data = null;
        $pos = clone $this->getLocation();
        $particle = Main::getInstance()->getParticle($name, $data);
        $random = new Random((int)(microtime(true) * 1000) + mt_rand());
        for ($i = 0; $i < $count; ++$i) {
            $this->getWorld()->addParticle($pos->add(
                $random->nextSignedFloat() * $xd,
                $random->nextSignedFloat() * $yd,
                $random->nextSignedFloat() * $zd
            ), $particle);
        }
    }

    public function attack(EntityDamageEvent $source): void
    {
        $source->call();
        if ($source->isCancelled()) {
            return;
        }

        $this->setLastDamageCause($source);

        $this->setHealth($this->getHealth() - $source->getFinalDamage());
        $this->doHitAnimation();
    }

    public function canBeCollidedWith(): bool
    {
        return false;
    }

    private function growth(): void
    {
        if (!$this->hasFuel()) return;
        $plants = $this->getPlants();
        foreach ($plants as $plant) {
            if (($plant instanceof Crops or $plant instanceof NetherWartPlant) and $plant->getAge() + 1 <= $plant::MAX_AGE) {
                $plant->setAge($plant->getAge() + 1);
                $this->getWorld()->setBlock($plant->getPosition(), $plant);
                $this->getWorld()->addParticle($plant->getPosition(), new HeartParticle());
            }
            else if($plant instanceof Sugarcane){
                $pos = $plant->getPosition()->add(0, 1, 0);
                if($this->getWorld()->getBlock($pos) instanceof Air){
                    $this->getWorld()->setBlock($pos, VanillaBlocks::SUGARCANE());
                    $this->getWorld()->addParticle($pos->add(0, 1, 0), new HeartParticle());
                }
            }
        }
        $this->removeFuel(1);
    }

    private function hasFuel(): bool
    {
        return $this->fuel > 0;
    }

    private function onArea(Position $position): bool
    {
        $radius = Main::$radius;
        return ($position->x >= (int)$this->getLocation()->asPosition()->getX() - $radius and $position->x <= (int)$this->getLocation()->asPosition()->getX() + $radius) and
            ($position->y === (int)$this->getLocation()->asPosition()->getY()) and
            ($position->z >= (int)$this->getLocation()->asPosition()->getZ() - $radius and $position->z <= (int)$this->getLocation()->asPosition()->getZ() + $radius) and
            $position->getWorld()->getDisplayName() === $this->getWorld()->getDisplayName();
    }

    private function getPlants(): array
    {
        $plants = [];
        $radius = Main::$radius;
        for ($x = $this->getLocation()->asPosition()->getX() - $radius; $x <= $this->getLocation()->asPosition()->getX() + $radius; $x++) {
            for ($z = $this->getLocation()->asPosition()->getZ() - $radius; $z <= $this->getLocation()->asPosition()->getZ() + $radius; $z++) {
                $plants[] = $this->getWorld()->getBlock(new Vector3($x, $this->getLocation()->asPosition()->getY(), $z));
            }
        }
        return $plants;
    }
}
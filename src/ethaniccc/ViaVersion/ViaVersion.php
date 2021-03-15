a<?php

declare(strict_types=1);

namespace ethaniccc\ViaVersion;

use ethaniccc\ViaVersion\hacks\v419\PlayerListPacket419;
use ethaniccc\ViaVersion\hacks\v428\PlayerListPacket428;
use ethaniccc\ViaVersion\hacks\v428\SkinData428;
use ethaniccc\ViaVersion\hacks\v428\StartGamePacket428;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\network\mcpe\protocol\BatchPacket;
use pocketmine\network\mcpe\protocol\PacketPool;
use pocketmine\network\mcpe\protocol\PlayerActionPacket;
use pocketmine\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\TextFormat;
use UnexpectedValueException;
use pocketmine\event\Listener;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\network\mcpe\protocol\LoginPacket;
use pocketmine\network\mcpe\protocol\PacketViolationWarningPacket;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\PlayerListPacket;
use pocketmine\network\mcpe\protocol\StartGamePacket;
use pocketmine\plugin\PluginBase;

class ViaVersion extends PluginBase implements Listener
{

    public const SUPPORTED_PROTOCOLS = [419, 422, 428];

    private $protocol = [];
    private $fabID = [];
    private $lastSentPacket = [];
    private $players = [];

    public function onEnable()
    {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        /* $this->getScheduler()->scheduleDelayedTask(new ClosureTask(function(int $currentTick) : void{
            $plugin = $this->getServer()->getPluginManager()->getPlugin("Mockingbird");
            if($plugin !== null){
                $this->getServer()->getPluginManager()->disablePlugin($plugin);
            }
            // Mockingbird will break the server because of PlayerActionPacket not being sent
            // when playerMovementMode is changed... #BlameMicrojang
        }), 1); */
    }

    public function receivePacket(DataPacketReceiveEvent $event): void
    {
        $packet = $event->getPacket();
        if ($packet instanceof LoginPacket && in_array($packet->protocol, self::SUPPORTED_PROTOCOLS)) {
            $player = spl_object_hash($event->getPlayer());
            $this->protocol[$player] = $packet->protocol;
            if ($packet->protocol >= 428) {
                $this->fabID[$player] = $packet->clientData["PlayerFabId"] ?? "";
            }
            $packet->protocol = ProtocolInfo::CURRENT_PROTOCOL;
            if (!isset($this->players[($packet->username)]))
                $this->players[($packet->username)] = $event->getPlayer();
        } elseif ($packet instanceof PacketViolationWarningPacket) {
            $this->getLogger()->debug("Client error: {$packet->getMessage()}");
            var_dump($this->lastSentPacket[spl_object_hash($event->getPlayer())] ?? "no found last sent");
        }
    }

    public function send(DataPacketSendEvent $event): void
    {
        $packet = $event->getPacket();
        $player = $event->getPlayer();
        if (get_class($packet) === PlayerListPacket::class) {
            /** @var PlayerListPacket $packet */
            try {
                $packet->decode();
            } catch (\RuntimeException $e) {
            }
            $hash = spl_object_hash($player);
            $protocol = $this->protocol[$hash];
            if ($protocol > 422 && $protocol !== ProtocolInfo::CURRENT_PROTOCOL) {
                $event->setCancelled();
                $enteries = [];
                foreach ($packet->entries as $entry) {
                    if ($entry->username === null && $entry->entityUniqueId === null) {
                        continue;
                    } else {
                        if (!isset($this->players[($entry->username)])) return;
                        $p = $this->players[($entry->username)] ?? null;
                    }
                    if ($p === null)
                        return;
                    $h = spl_object_hash($p);
                    $oP = $this->protocol[$h] ?? 419;
                    switch ($oP) {
                        case 428:
                            $entry->skinData = SkinData428::from($entry->skinData, $this->fabID[$hash]);
                            break;
                        default:
                            // idk what to put here lol....
                            $entry->skinData = SkinData428::from($entry->skinData, "8a6bfa18-cfdd-46aa-a479-56b194cda178");
                            break;
                    };
                    $enteries[] = $entry;
                }
                $pk = new PlayerListPacket428();
                $pk->entries = $enteries;
                $pk->type = $packet->type;
                $this->getLogger()->debug("sent new player list packet");
                $player->sendDataPacket($pk, false, true);
            } elseif ($protocol <= 422 && $protocol !== ProtocolInfo::CURRENT_PROTOCOL) {
                $event->setCancelled();
                $enteries = [];
                foreach ($packet->entries as $entry) {
                    $enteries[] = $entry;
                }
                $pk = new PlayerListPacket419();
                $pk->entries = $enteries;
                $pk->type = $packet->type;
                $player->sendDataPacket($pk, false, true);
            }
        } elseif (get_class($packet) === StartGamePacket::class) {
            /** @var StartGamePacket $packet */
            $hash = spl_object_hash($player);
            $protocol = $this->protocol[$hash];
            if ($protocol > 422 && $protocol !== ProtocolInfo::CURRENT_PROTOCOL) {
                $event->setCancelled();
                $pk = StartGamePacket428::from($packet);
                $this->getLogger()->debug("sent new start game packet");
                $player->sendDataPacket($pk, false, true);
            } else {
                $this->getLogger()->debug("failed conditions");
            }
        } elseif ($packet instanceof BatchPacket) {
            $gen = $packet->getPackets();
            foreach ($packet->getPackets() as $buff) {
                $pk = PacketPool::getPacket($buff);
                try {
                    $pk->decode();
                } catch (\Exception $e) {
                    continue;
                }
                if (get_class($pk) === PlayerListPacket::class) {
                    /** @var PlayerListPacket $pk */
                    if (count($pk->entries) === 0)
                        return;
                    foreach ($pk->entries as $entry) {
                        if ($entry->skinData instanceof SkinData428) {
                            $this->getLogger()->debug("oh sh1t nibba fucced up");
                            return;
                        }
                    }
                    $hash = spl_object_hash($player);
                    $protocol = $this->protocol[$hash];
                    if ($protocol > 422 && $protocol !== ProtocolInfo::CURRENT_PROTOCOL) {
                        $enteries = [];
                        foreach ($pk->entries as $entry) {
                            if ($entry->username === null && $entry->entityUniqueId === null) {
                                continue;
                            } else {
                                if (!isset($this->players[($entry->username)])) return;
                                $p = $this->players[($entry->username)];
                                $this->getLogger()->debug("username entry={$entry->username}");
                            }
                            if ($p === null)
                                return;
                            $h = spl_object_hash($p);
                            $oP = $this->protocol[$h] ?? 419;
                            switch ($oP) {
                                case 428:
                                    $entry->skinData = SkinData428::from($entry->skinData, $this->fabID[$hash]);
                                    break;
                                default:
                                    // idk what to put here lol....
                                    $entry->skinData = SkinData428::from($entry->skinData, "8a6bfa18-cfdd-46aa-a479-56b194cda178");
                                    break;
                            };
                            $enteries[] = $entry;
                        }
                        $pKK = new PlayerListPacket428();
                        $pKK->entries = $enteries;
                        $pKK->type = $pk->type;
                        $this->getLogger()->debug("sent new player list packet (batch)");
                        $player->sendDataPacket($pKK, false, true);
                        $event->setCancelled();
                    }/* elseif($protocol <= 419 && $protocol !== ProtocolInfo::CURRENT_PROTOCOL){
                        $event->setCancelled();
                        $enteries = [];
                        foreach($pk->entries as $entry){
                            $enteries[] = $entry;
                        }
                        $pKK = new PlayerListPacket419();
                        $pKK->entries = $enteries;
                        $pKK->type = $pk->type;
                        $player->sendDataPacket($pKK, false, true);
                    } */
                }
            }
        }

        $this->lastSentPacket[spl_object_hash($player)] = $packet;
    }

    public function leave(PlayerQuitEvent $event): void
    {
        unset($this->players[$event->getPlayer()->getName()]);
    }
    public function onAttack(EntityDamageByEntityEvent $ev)
    {
        $damaged = $ev->getEntity();
        $damager = $ev->getDamager();

        if($damaged instanceof Player && $damager instanceof Player)
        {
            if ($damaged->getGamemode() != 0 || $damaged->getGamemode() != 3)
            {
                $ev->setCancelled();
            }
            if ($damaged->getLevel() == "world")
            {
                $ev->setCancelled();
            }
            $damaged->knockBack($damager, $ev->getFinalDamage(), (float)$damaged->getX(), (float) $damaged->getZ());

        }
    }
}

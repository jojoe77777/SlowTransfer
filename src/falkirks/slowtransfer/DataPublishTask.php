<?php
namespace falkirks\slowtransfer;


use pocketmine\Player;
use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;

class DataPublishTask extends AsyncTask{
    private $player;
    private $address;
    private $port;
    private $message;
    private $applicationPort;
    private $data;

    private $success;

    public function __construct($player, $address, $port, $message, $applicationPort, $data){
        $this->player = ($player instanceof Player ? $player->getName() : $player);
        $this->address = $address;
        $this->port = $port;
        $this->message = $message;
        $this->applicationPort = $applicationPort;
        $this->data = serialize($data);
        $this->success = false;
    }


    /**
     * Actions to execute when run
     *
     * @return void
     */
    public function onRun(){
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if($socket !== false) {
            $result = socket_connect($socket, $this->address, $this->applicationPort);
            if($result !== false) {
                socket_write($socket, SlowTransfer::PROTOCOL_IDENTIFIER . "\n");
                socket_write($socket, "PUBLISH\n");
                socket_write($socket, $this->player . "\n");
                $data = unserialize($this->data);
                foreach ($data as $namespace => $value) {
                    $value = serialize($value);
                    socket_write($socket, "$namespace\n");
                    socket_write($socket, strlen($value) . "\n");
                    socket_write($socket, $value);
                }
                socket_write($socket, "STOP\n");
                if (socket_read($socket, 128, PHP_NORMAL_READ) === SlowTransfer::PROTOCOL_IDENTIFIER) {
                    if (socket_read($socket, 64, PHP_NORMAL_READ) === "WAITING") {
                        $this->success = true;
                    }
                }
            }
            @socket_shutdown($socket);
            socket_close($socket);
        }
    }

    public function onCompletion(Server $server){
        $player = $server->getPlayer($this->player);
        if($player instanceof Player){
        	$player->transfer($this->address, $this->port, $this->message);
        }
    }
}
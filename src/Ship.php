<?php
    class Ship
    {
        private $name;
        private $cargo_capacity;
        private $fuel_capacity;
        private $credits;
        private $location_x;
        private $location_y;
        private $current_fuel;
        private $turn;
        private $id;

        function __construct($name, $cargo_capacity, $fuel_capacity, $credits, $location_x, $location_y, $current_fuel, $turn, $id = null)
        {
            $this->name = $name;
            $this->cargo_capacity = $cargo_capacity;
            $this->fuel_capacity = $fuel_capacity;
            $this->credits = $credits;
            $this->location_x = $location_x;
            $this->location_y = $location_y;
            $this->current_fuel = $current_fuel;
            $this->turn = $turn;
            $this->id = $id;
        }

        function getTurn()
        {
            return $this->turn;
        }

        function getId()
        {
            return $this->id;
        }

        function getName()
        {
            return $this->name;
        }

        function getCargoCapacity()
        {
            return $this->cargo_capacity;
        }

        function getFuelCapacity()
        {
            return $this->fuel_capacity;
        }

        function getCredits()
        {
            return $this->credits;
        }

        function getLocation()
        {
            return array($this->location_x, $this->location_y);
        }

        function getCurrentFuel()
        {
            return $this->current_fuel;
        }

        function setId($new_id)
        {
            $this->id = (int) $new_id;
        }

        function setCapacities($new_cargo, $new_fuel)
        {
            $this->cargo_capacity = (int) $new_cargo;
            $this->fuel_capacity = (int) $new_fuel;
        }

        function setName($new_name)
        {
            $this->name = (string) $new_name;
        }

        function setCredits($new_credits)
        {
            $this->credits = (int) $new_credits;
        }

        function setLocation($new_x, $new_y)
        {
            $this->location_x = (int) $new_x;
            $this->location_y = (int) $new_y;
        }

        function setCurrentFuel($new_fuel)
        {
            $this->current_fuel = (int) $new_fuel;
        }

        function save()
        {
          $GLOBALS['DB']->exec("INSERT INTO ship (
            name,
            cargo_capacity,
            fuel_capacity,
            credits,
            location_x,
            location_y,
            current_fuel,
            turn
          ) VALUES (
            '{$this->getName()}',
            {$this->getCargoCapacity()},
            {$this->getFuelCapacity()},
            {$this->getCredits()},
            {$this->getLocation()[0]},
            {$this->getLocation()[1]},
            {$this->getCurrentFuel()},
            {$this->getTurn()}
          );");
          $this->id = $GLOBALS['DB']->lastInsertId();
        }

        static function getAll()
        {
            $returned_ships = $GLOBALS['DB']->query("SELECT * FROM ship;");
            $ships = array();
            foreach($returned_ships as $ship) {
                $id = $ship['id'];
                $name = $ship['name'];
                $cargo_capacity = $ship['cargo_capacity'];
                $fuel_capacity = $ship['fuel_capacity'];
                $credits = $ship['credits'];
                $location_x = $ship['location_x'];
                $location_y = $ship['location_y'];
                $current_fuel = $ship['current_fuel'];
                $turn = $ship['turn'];
                $new_ship = new Ship($name, $cargo_capacity, $fuel_capacity, $credits, $location_x, $location_y, $current_fuel, $turn, $id);
                array_push($ships, $new_ship);
            }
            return $ships;
        }

        static function deleteAll()
        {
            $GLOBALS['DB']->exec("DELETE FROM ship;");
            $GLOBALS['DB']->exec("DELETE FROM cargo;");
        }

        function checkGameover()
        {
            $current_planet = Planet::findByCoordinates($this->location_y, $this->location_x);

            // if turns has reached maximum turns
            if($this->turn >= System::getGameplayParameters()['max_turns']) {
                return 1;
            }
            // if out of credits and cargo
            if ($this->credits <= 0 && $this->getCargoLoad() <= 0) {
                return 2;
            }
            // if stranded without fuel or means to buy fuel
            if ($current_planet->getType() != 3 && $this->current_fuel < 10) {
                return 3;
            }
            return 0;
        }

        function nextTurn()
        {
            $this->turn++;
        }

        static function find($search_id)
        {
            $found_ship = null;
            $ships = Ship::getAll();
            foreach($ships as $ship) {
                $ship_id = $ship->getId();
                if ($ship_id == $search_id) {
                  $found_ship = $ship;
                }
            }
            return $found_ship;
        }

        function update()
        {
            $GLOBALS['DB']->exec("UPDATE ship SET
            name = '{$this->getName()}',
            cargo_capacity = {$this->getCargoCapacity()},
            fuel_capacity = {$this->getFuelCapacity()},
            credits = {$this->getCredits()},
            location_x = {$this->getLocation()[0]},
            location_y = {$this->getLocation()[1]},
            current_fuel = {$this->getCurrentFuel()},
            turn = {$this->getTurn()}
            WHERE id = {$this->getId()};");
        }

        function delete()
        {
            $GLOBALS['DB']->exec("DELETE FROM ship WHERE id = {$this->getId()};");
            $GLOBALS['DB']->exec("DELETE FROM cargo WHERE ship_id = {$this->getId()};");
        }

        function getDistance($destination_x, $destination_y)
        {
            $delta_x = abs($destination_x - $this->getLocation()[0]);
            $delta_y = abs($destination_y - $this->getLocation()[1]);
            return ceil(sqrt(pow($delta_x, 2) + pow($delta_y, 2)));
        }

        function checkTravelRange($destination_x, $destination_y)
        {
            $distance = $this->getDistance($destination_x, $destination_y);
            if ($distance * 10 <= $this->getCurrentFuel()) {
                return true;
            } else {
                return false;
            }
        }

        function travel($destination_x, $destination_y)
        {
            if ($this->checkTravelRange($destination_x, $destination_y)) {
                $distance = $this->getDistance($destination_x, $destination_y);
                $this->setLocation($destination_x, $destination_y);
                $this->current_fuel -= $distance * System::getGameplayParameters()['travel_cost'];
                $this->credits -= System::getGameplayParameters()['upkeep_cost'];
            } else {
                return;
            }
        }

        function purchaseFuelCheck($fuel_purchase_amount, $fuel_price)
        {
            $fuel_cost = $fuel_purchase_amount * $fuel_price;
            if ($fuel_cost > $this->getCredits()) {
                return false;
            } elseif ($this->fuel_capacity - $this->current_fuel < $fuel_purchase_amount) {
                return false;
            } else {
                return true;
            }
        }

        function purchaseFuel($fuel_purchase_amount, $fuel_price)
        {
            if ($this->purchaseFuelCheck($fuel_purchase_amount, $fuel_price)) {
                $this->current_fuel += $fuel_purchase_amount;
                $this->credits -= ($fuel_purchase_amount * $fuel_price);
            }
        }

        function initializeCargo()
        {
            $tradegoods = TradeGood::getAll();
            foreach ($tradegoods as $tradegood) {
                $new_cargo = new Cargo($tradegood->getId(), $this->getId(), 0);
                $new_cargo->save();
            }
        }

        function getCargoManifest()
        {
            $return = array();
            $all_cargo = Cargo::getAll();
            foreach ($all_cargo as $cargo) {
                if ($cargo->getShipId() == $this->getId()) {
                    array_push($return, $cargo);
                }
            }
            return $return;
        }

        function creditCheck($unit_price, $purchase_quantity)
        {
            if (($unit_price * $purchase_quantity) > $this->getCredits()) {
                return false;
            } else {
                return true;
            }
        }

        function findCargo($cargo_type)
        {
            $found_cargo = null;
            $manifest = $this->getCargoManifest();
            foreach ($manifest as $cargo) {
                if (TradeGood::find($cargo->getTradeGoodsId())->getName() == $cargo_type) {
                    $found_cargo = $cargo;
                }
            }
            return $found_cargo;
        }

        function addCargo($cargo_type, $cargo_quantity)
        {
            $cargo = $this->findCargo($cargo_type);
            $cargo->update($cargo->getQuantity() + $cargo_quantity);
        }

        function getCargoLoad()
        {
            $current_load = 0;
            $manifest = $this->getCargoManifest();
            foreach ($manifest as $cargo) {
                $current_load += $cargo->getQuantity();
            }
            return $current_load;
        }

        function cargoCheck($new_cargo_quantity)
        {
            if (($this->getCargoCapacity() - $this->getCargoLoad()) < $new_cargo_quantity) {
                return false;
            } else {
                return true;
            }
        }

        function buyTradeGood($cargo_type, $purchase_quantity, $unit_price)
        {
            if ($this->cargoCheck($purchase_quantity) && $this->creditCheck($unit_price, $purchase_quantity)) {
                $cargo = $this->findCargo($cargo_type);
                $cargo->update($cargo->getQuantity() + $purchase_quantity);
                $this->credits -= $unit_price * $purchase_quantity;
            }
        }

        function sellTradeGood($cargo_type, $sale_quantity, $unit_price)
        {
            $cargo = $this->findCargo($cargo_type);
            if ($sale_quantity <= $cargo->getQuantity()) {
                $cargo->update($cargo->getQuantity() - $sale_quantity);
                $this->credits += $unit_price * $sale_quantity;
            }
        }
    }
?>

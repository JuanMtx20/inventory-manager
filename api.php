<?php

// Database class to handle all database operations
class Database
{
  private $host = 'localhost';
  private $db_name = 'inventory_manager';
  private $password = 'password';
  private $database = 'inventory_manager';
  private $conn;

  public function getConnection()
  {
    $this->conn = null;

    try {
      $this->conn = new PDO("mysql:host=$this->host;dbname=$this->database", $this->db_name, $this->password);
      $this->conn->exec('set names utf8');
    } catch (PDOException $exception) {
      echo 'Connection error: ' . $exception->getMessage();
    }

    return $this->conn;
  }

  public function closeConnection()
  {
    $this->conn = null;
  }

  public function __destruct()
  {
    $this->closeConnection();
  }

  public function beginTransaction()
  {
    $this->conn->beginTransaction();
  }

  public function commit()
  {
    $this->conn->commit();
  }

  public function rollback()
  {
    $this->conn->rollback();
  }

  public function lastInsertId()
  {
    return $this->conn->lastInsertId();
  }

  public function prepare($sql)
  {
    return $this->conn->prepare($sql);
  }

  public function exec($sql)
  {
    return $this->conn->exec($sql);
  }

  public function fetchColumn($sql)
  {
    return $this->conn->query($sql)->fetchColumn();
  }

  public function fetchAll($sql)
  {
    return $this->conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
  }

  public function rowCount($sql)
  {
    return $this->conn->query($sql)->rowCount();
  }

  public function query($sql)
  {
    return $this->conn->query($sql);
  }

  public function bindParam($param, $value)
  {
    return $this->conn->bindParam($param, $value);
  }

  public function fetch()
  {
    return $this->conn->fetch();
  }
}

/**
 * Class Item
 */
class Item
{
  private $db;

  /**
   * Item constructor.
   * @param PDO $db The database connection object.
   */
  public function __construct($db)
  {
    $this->db = $db;
  }

  /**
   * Retrieves all items from the database.
   * @return array An array of items.
   */
  public function get()
  {
    $stmt = $this->db->prepare("SELECT * FROM items");
    $stmt->execute();
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return $items;
  }

  /**
   * Retrieves items by ids from the database.
   * @param string $ids The ids of the items to retrieve.
   * @return array An array of items.
   */
  public function getByIds($ids)
  {
    $stmt = $this->db->prepare("SELECT * FROM items WHERE id IN ($ids)");
    $stmt->execute();
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return $items;
  }
}

class User
{
  private $db;

  /**
   * Item constructor.
   * @param PDO $db The database connection object.
   */
  public function __construct($db)
  {
    $this->db = $db;
  }

  /**
   * Retrieves all items from the database.
   * @return array An array of items.
   */
  public function get()
  {
    $stmt = $this->db->prepare("SELECT * FROM users");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return $users;
  }

  /**
   * Retrieves items by ids from the database.
   * @param string $ids The ids of the items to retrieve.
   * @return array An array of items.
   */
  public function getByIds($ids)
  {
    $stmt = $this->db->prepare("SELECT * FROM users WHERE usr_id IN ($ids)");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return $users;
  }
}

class ItemType
{
  private $db;

  /**
   * Item constructor.
   * @param PDO $db The database connection object.
   */
  public function __construct($db)
  {
    $this->db = $db;
  }

  /**
   * Retrieves all items from the database.
   * @return array An array of items.
   */
  public function get()
  {
    $stmt = $this->db->prepare("SELECT * FROM item_types");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return $users;
  }

  /**
   * Retrieves items by ids from the database.
   * @param string $ids The ids of the items to retrieve.
   * @return array An array of items.
   */
  public function getByIds($ids)
  {
    $stmt = $this->db->prepare("SELECT * FROM item_types WHERE id IN ($ids)");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return $users;
  }
}


class Request
{
  private $db;

  /**
   * Retrieves all items from the database.
   * @return array An array of items.
   */
  public function __construct($db)
  {
    $this->db = $db;
  }

  /**
   * Retrieves all items from the database.
   * @return array An array of items.
   */
  public function get()
  {
    $stmt = $this->db->prepare("SELECT * FROM requests");
    $stmt->execute();
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // get the user name for each request
    $user = new User($this->db);
    $users = $user->getByIds(implode(',', array_map(function ($request) {
      return $request['requested_by'];
    }, $requests)));

    // match request with user name
    $requests = array_map(function ($request) use ($users) {
      $request['requested_by'] = array_values(array_filter($users, function ($user) use ($request) {
        return $user['usr_id'] === $request['requested_by'];
      }))[0]['name'];
      return $request;
    }, $requests);

    // we need to get item ids from the request and fetch the item details
    $requestItems = array_map(function ($request) {
      return json_decode($request['items'], true);
    }, $requests);

    // get the item ids from the items array
    $itemsIds = array_map(function ($items) {
      return array_map(function ($item) {
        return array_keys($item)[0];
      }, $items);
    }, $requestItems);

    // flatten the array and remove duplicates to get the item ids as a single array
    $itemsIds = array_unique(array_merge(...$itemsIds));

    $itemTypeIds = array_map(function ($items) {
      return array_map(function ($item) {
        return array_values($item)[0];
      }, $items);
    }, $requestItems);

    // flatten the array and remove duplicates to get the item ids as a single array
    $itemTypeIds = array_unique(array_merge(...$itemTypeIds));

    // the items all have the same item type so we can just get the first item type name
    $item_type = new ItemType($this->db);
    // pass the items ids joined by comma to get the item type name
    $item_type_name = $item_type->getByIds(implode(',', $itemTypeIds));

    $itemsDetailed = new Item($this->db);
    // pass the items ids joined by comma to get the item details
    $items = $itemsDetailed->getByIds(implode(',', $itemsIds));

    /**
     * Format the response
     * id: <request id>
     * requested_by: <user name>
     * items: [{<item1 name>,<item2 name>,<item3 name>}]
     * item_type: <item-type-name>
     *
     */
    $requests = array_map(function ($request) use ($items, $item_type_name) {
      $requestItems = json_decode($request['items'], true); // items ids from the request
      // get the item names from the $items array
      $requestItemsIds = array_map(function ($item) {
        return array_keys($item)[0];
      }, $requestItems);
      $itemsNames = array_map(function ($item) use ($items) {
        return array_values(array_filter($items, function ($itemDetail) use ($item) {
          return $itemDetail['id'] == $item;
        }))[0]['item'];
      }, $requestItemsIds);
      $request['items'] = implode(', ', $itemsNames);
      $first_item_type = array_values($requestItems[0])[0];
      // we have to dig into $items to get the item type id
      $request['item_type'] = array_values(array_filter($item_type_name, function ($item_type) use ($first_item_type) {
        return $item_type['id'] === $first_item_type;
      }))[0]['type'];
      return $request;
    }, $requests);
    return $requests;
  }

  /**
   * Retrieves an item by id from the database.
   * @return object An item object.
   */
  public function getById($req_id)
  {
    $stmt = $this->db->prepare("SELECT * FROM requests WHERE req_id = :req_id");
    $stmt->bindParam(':req_id', $req_id);
    $stmt->execute();
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    // return items as json
    $request['items'] = json_decode($request['items'], true);

    return $request;
  }

  /**
   * Creates a new request.
   * @param string $user The user who created the request.
   * @param array $items The items in the request.
   */
  public function create($user, $items)
  {
    try {
      // Step 1: get the items by id to get the item type id
      $item = new Item($this->db);
      $items = $item->getByIds($items);
      // format the items array to include the item type id
      // format: [{<item1 id>,<item-type1 id>}, {<item2 id>,<item-type1 id>}, {<item3 id>,<item-type2 id>}]
      // eg: [{1,1}, {2,1}, {3,2}]
      $items = array_map(function ($item) {
        return [
          $item['id'] => $item['item_type']
        ];
      }, $items);

      // Step 2: Insert the new request into the 'requests' table
      $stmt = $this->db->prepare("INSERT INTO requests (requested_by, requested_on, ordered_on, items) VALUES (:requested_by, :requested_on, :ordered_on, :items)");
      $today = date('Y-m-d');
      $stmt->bindParam(':requested_by', $user);
      $stmt->bindParam(':requested_on', $today);
      $stmt->bindParam(':ordered_on', $today);
      $stmt->bindParam(':items', json_encode($items));
      $stmt->execute();
    } catch (Exception $e) {
      // Handle any errors and rollback the transaction if an error occurs
      $this->db->rollback();
      throw $e;
    }
  }

  /**
   * Updates a request.
   * @param string $user The user who created the request.
   * @param array $items The items in the request.
   */
  public function update($req_id, $user, $items)
  {
    // start a transaction
    $this->db->beginTransaction();
    try {
      // Step 1: get the items by id to get the item type id
      $item = new Item($this->db);
      $items = $item->getByIds($items);
      // format the items array to include the item type id
      // format: [{<item1 id>,<item-type1 id>}, {<item2 id>,<item-type1 id>}, {<item3 id>,<item-type2 id>}]
      // eg: [{1,1}, {2,1}, {3,2}]
      $items = array_map(function ($item) {
        return [
          $item['id'] => $item['item_type']
        ];
      }, $items);

      // Step 2: Update the request in the 'requests' table
      $stmt = $this->db->prepare("UPDATE requests SET requested_by = :requested_by, items = :items WHERE req_id = :req_id");
      $stmt->bindParam(':requested_by', $user);
      $stmt->bindParam(':items', json_encode($items));
      $stmt->bindParam(':req_id', $req_id);
      if ($stmt->execute()) {
        // commit the transaction
        $this->db->commit();
      }
    } catch (Exception $e) {
      // Handle any errors and rollback the transaction if an error occurs
      $this->db->rollback();
      throw $e;
    }
  }

  /**
   * Deletes a request.
   * @param string $req_id The id of the request to delete.
   */
  public function delete($req_id)
  {
    // start a transaction
    $this->db->beginTransaction();
    try {
      // Step 1: Delete the request from the 'requests' table
      $stmt = $this->db->prepare("DELETE FROM requests WHERE req_id = :req_id");
      $stmt->bindParam(':req_id', $req_id);
      if ($stmt->execute()) {
        // commit the transaction
        $this->db->commit();
      }
    } catch (Exception $e) {
      // Handle any errors and rollback the transaction if an error occurs
      $this->db->rollback();
      throw $e;
    }
  }

  public function getItemsByUserAndDate($requested_by, $ordered_on)
  {
    try {
      $stmt = $this->db->prepare("SELECT * FROM requests WHERE ordered_on = :ordered_on AND requested_by = :requested_by");
      $stmt->bindParam(':ordered_on', $ordered_on);
      $stmt->bindParam(':requested_by', $requested_by);
      $stmt->execute();
      $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
      $items = array_map(function ($item) {
        return [
          $item['item_type'] => $item['items']
        ];
      }, $items);
      return $items;
    } catch (Exception $e) {
      // Handle any errors and rollback the transaction if an error occurs
      $this->db->rollback();
      throw $e;
    }
  }
}

class Summary
{
  private $db;

  /**
   * Retrieves all items from the database.
   * @return array An array of items.
   */
  public function __construct($db)
  {
    $this->db = $db;
  }

  /**
   * Now the system takes a snapshot on the day of ordering and feeds info in 'summary'.
   * Here the system aggregates all requests for each person in the 'items' column. 
   */
  public function update($requested_by, $ordered_on)
  {
    try {
      // Step 1: get the items by ordered_on and user
      $request = new Request($this->db);
      $items = $request->getItemsByUserAndDate($requested_by, $ordered_on);

      $items = array_map(function ($item) {
        return json_decode(array_values($item)[0], true);
      }, $items);

      $groupedItems = [];
      foreach ($items as $key => $itemList) {
        foreach ($itemList as $key => $singleItem) {
          $item_id = array_keys($singleItem)[0];
          $item_cat = array_values($singleItem)[0];
          if (!isset($groupedItems[$item_cat])) {
            $groupedItems[$item_cat] = [];
          }
          $groupedItems[$item_cat][] = $item_id;
        }
      }

      // start a transaction
      $this->db->beginTransaction();
      // update or create the summary for the given user and date
      $stmt = $this->db->prepare("SELECT * FROM summary WHERE ordered_on = :ordered_on AND requested_by = :requested_by");
      $stmt->bindParam(':ordered_on', $ordered_on);
      $stmt->bindParam(':requested_by', $requested_by);
      $stmt->execute();
      $summary = $stmt->fetchAll(PDO::FETCH_ASSOC);
      if ($summary) {
        // update the summary
        $stmt = $this->db->prepare("UPDATE summary SET items = :items WHERE ordered_on = :ordered_on AND requested_by = :requested_by");
        $stmt->bindParam(':ordered_on', $ordered_on);
        $stmt->bindParam(':items', json_encode($groupedItems));
        $stmt->bindParam(':requested_by', $requested_by);
        if ($stmt->execute()) {
          // commit the transaction
          $this->db->commit();
        }
      } else {
        // create the summary
        $stmt = $this->db->prepare("INSERT INTO summary (requested_by, ordered_on, items) VALUES (:requested_by, :ordered_on, :items)");
        $stmt->bindParam(':ordered_on', $ordered_on);
        $stmt->bindParam(':requested_by', $requested_by);
        $stmt->bindParam(':items', json_encode($groupedItems));
        if ($stmt->execute()) {
          // commit the transaction
          $this->db->commit();
        }
      }
    } catch (Exception $e) {
      // Handle any errors and rollback the transaction if an error occurs
      $this->db->rollback();
      throw $e;
    }
  }
}



class ResponseHandler
{
  static function handleSuccess($response)
  {
    echo json_encode(['status' => 'success', 'data' => $response]);
  }

  static function handleError($error, $payload = null)
  {
    echo json_encode(['status' => 'error', 'message' => $error, 'payload' => $payload]);
  }
}

header('Content-Type: application/json');

// Handle the requests based on the endpoint
// we use switch case to handle multiple endpoints
switch (true) {
  case ($_SERVER['REQUEST_METHOD'] === 'GET' && $_GET['endpoint'] === 'users'):
    try {
      // create a new instance of the Database class
      $database = new Database();
      // get the database connection
      $db = $database->getConnection();
      // create a new instance of the User class and pass the database connection as a parameter to the constructor using dependency injection
      $user = new User($db);
      // call the get method to get all users
      $result = $user->get();
      // close the database connection
      $database->closeConnection();
      // return the response
      ResponseHandler::handleSuccess($result);
    } catch (Exception $e) {
      // return the error message
      ResponseHandler::handleError($e->getMessage());
    }
    break;
  case ($_SERVER['REQUEST_METHOD'] === 'GET' && $_GET['endpoint'] === 'items'):
    try {
      // create a new instance of the Database class
      $database = new Database();
      // get the database connection
      $db = $database->getConnection();
      // create a new instance of the Item class and pass the database connection as a parameter to the constructor using dependency injection
      $item = new Item($db);
      // call the get method to get all items
      $result = $item->get();
      // close the database connection
      $database->closeConnection();
      // return the response
      ResponseHandler::handleSuccess($result);
    } catch (Exception $e) {
      // return the error message
      ResponseHandler::handleError($e->getMessage());
    }
    break;
  case ($_SERVER['REQUEST_METHOD'] === 'GET' && $_GET['endpoint'] === 'requests' && $_GET['id']):
    try {
      // create a new instance of the Database class
      $database = new Database();
      // get the database connection
      $db = $database->getConnection();
      // create a new instance of the Request class and pass the database connection as a parameter to the constructor using dependency injection
      $request = new Request($db);
      // call the get method to get the request by id
      $result = $request->getById($_GET['id']);
      // close the database connection
      $database->closeConnection();
      // return the response
      ResponseHandler::handleSuccess($result);
    } catch (Exception $e) {
      // return the error message
      ResponseHandler::handleError($e->getMessage());
    }
    break;
  case ($_SERVER['REQUEST_METHOD'] === 'GET' && $_GET['endpoint'] === 'requests'):
    try {
      // create a new instance of the Database class
      $database = new Database();
      // get the database connection
      $db = $database->getConnection();
      // create a new instance of the Request class and pass the database connection as a parameter to the constructor using dependency injection
      $request = new Request($db);
      // call the get method to get all requests
      $result = $request->get();
      // close the database connection
      $database->closeConnection();
      // return the response
      ResponseHandler::handleSuccess($result);
    } catch (Exception $e) {
      // return the error message
      ResponseHandler::handleError($e->getMessage());
    }
    break;
  case ($_SERVER['REQUEST_METHOD'] === 'POST' && $_GET['endpoint'] === 'requests' && $_GET['id']):
    try {
      // create a new instance of the Database class
      $database = new Database();
      // get the database connection
      $db = $database->getConnection();
      // create a new instance of the Request class and pass the database connection as a parameter to the constructor using dependency injection
      $request = new Request($db);
      // get the user and request items from the request body
      $request->update($_GET['id'], $_POST['user'], $_POST['items']);
      // after update the request we need to update the summary
      $summary = new Summary($db);
      $summary->update($_POST['user'], date('Y-m-d'));
      // close the database connection
      $database->closeConnection();
      // return the response
      ResponseHandler::handleSuccess('Request updated successfully');
    } catch (Exception $e) {
      ResponseHandler::handleError($e->getMessage(), $_POST);
    }
    break;
  case ($_SERVER['REQUEST_METHOD'] === 'POST' && $_GET['endpoint'] === 'requests'):
    try {
      // create a new instance of the Database class
      $database = new Database();
      // get the database connection
      $db = $database->getConnection();
      // create a new instance of the Request class and pass the database connection as a parameter to the constructor using dependency injection
      $request = new Request($db);
      // get the user and request items from the request body
      // we use $_POST to get the request body because we are using the 'application/x-www-form-urlencoded' content type
      // call the create method to create a new request
      $request->create($_POST['user'], $_POST['items']);
      // after create the request we need to update the summary
      $summary = new Summary($db);
      $summary->update($_POST['user'], date('Y-m-d'));
      // close the database connection
      $database->closeConnection();
      // return the response
      ResponseHandler::handleSuccess('Request created successfully');
    } catch (Exception $e) {
      ResponseHandler::handleError($e->getMessage());
    }
    break;
  case ($_SERVER['REQUEST_METHOD'] === 'DELETE' && $_GET['endpoint'] === 'requests'):
    try {
      // create a new instance of the Database class
      $database = new Database();
      // get the database connection
      $db = $database->getConnection();
      // create a new instance of the Request class and pass the database connection as a parameter to the constructor using dependency injection
      $request = new Request($db);
      // call the delete method to delete the request
      $request->delete($_GET['id']);
      // after create the request we need to update the summary
      $summary = new Summary($db);
      $summary->update($_POST['user'], date('Y-m-d'));
      // close the database connection
      $database->closeConnection();
      // return the response
      ResponseHandler::handleSuccess('Request deleted successfully');
    } catch (Exception $e) {
      ResponseHandler::handleError($e->getMessage(), $_GET);
    }
    break;
  default:
    echo json_encode(['status' => 'available']);
    break;
}

<?php

use Phalcon\Loader;
use Phalcon\Mvc\Micro;
use Phalcon\DI\FactoryDefault;
use Phalcon\Db\Adapter\Pdo\Mysql as PdoMysql;
use Phalcon\Http\Response;

// Use Loader() to autoload our model
$loader = new Loader();

$loader->registerDirs(array(
    __DIR__ . '/models/'
))->register();

$di = new FactoryDefault();

//Set up the database service
$di->set('db', function () {
    return new PdoMysql(array(
        "host" => "localhost",
        "username" => "robotics",
        "password" => "mypassword",
        "dbname" => "robotics"
    ));
});

//Create and bind the DI to the application
$app = new Micro($di);


$app->get('/', function () {
    header( 'Location: /apidoc/', true, 301 );
});


/**
 * @apiDefine RobotApiSuccessDesc
 *
 * @apiSuccess {Number} id Id of the Robot.
 * @apiSuccess {String} name  Name of the Robot.
 * @apiSuccess {String} type Type of the Robot.
 * @apiSuccess {Number} year  Year of the Robot.
 */


/**
 * @api {get} /robots/ Request All robots
 * @apiName GetRobots
 * @apiGroup Robot
 *
 *
 * @apiUse RobotApiSuccessDesc
 *
 * @apiSuccessExample Success-Response:
 *     HTTP/1.1 200 OK
 *       [
 *           {
 *               "id": "2",
 *               "name": "ASIMO",
 *               "type": "humanoid",
 *               "year": "2000"
 *           },
 *           {
 *               "id": "1",
 *               "name": "C-3PO",
 *               "type": "droid",
 *               "year": "1977"
 *           }
 *       ]
 *
 *
 * @apiError RobotNotFound Robots was not found.
 *
 * @apiErrorExample Error-Response:
 *     HTTP/1.1 404 Not Found
 *
 *
 */
$app->get('/robots', function () use ($app) {

    $phql = "SELECT * FROM Robots ORDER BY name";
    $robots = $app->modelsManager->executeQuery($phql);

    $data = array();
    foreach ($robots as $robot) {
        $data[] = array(
            'id' => $robot->id,
            'name' => $robot->name,
            'type' => $robot->type,
            'year' => $robot->year
        );
    }
    if (count($data) > 0) {
        echo json_encode($data);
    } else {
        $response = new Response();
        $response->setStatusCode(404, "Not Found");
        return $response;
    }

});


/**
 * @api {get} /robots/search/:name Search Robot
 * @apiName SearchRobot
 * @apiGroup Robot
 *
 * @apiParam {string} name Robots name.
 *
 * @apiUse RobotApiSuccessDesc
 *
 * @apiSuccessExample Success-Response:
 *     HTTP/1.1 200 OK
 *       [
 *          {
 *          "status":"FOUND",
 *          "data":
 *              {
 *                  "id":"1","name":"C-3PO","type":"droid","year":"1977"
 *              }
 *          }
 *       ]
 *
 * @apiError RobotNotFound The name of the Robot was not found.
 *
 * @apiErrorExample Error-Response:
 *     HTTP/1.1 404 Not Found
 *        {
 *                "status":"NOT-FOUND"
 *        }
 *
 */
$app->get('/robots/search/{name}', function ($name) use ($app) {
    $phql = "SELECT * FROM Robots WHERE name LIKE :name: ORDER BY name";
    $robots = $app->modelsManager->executeQuery($phql, array(
        'name' => '%' . $name . '%'
    ));
    $response = new Response();
    $data = array();
    foreach ($robots as $robot) {

        if (!empty($robot->id)) {
            $data[] = array(
                'status' => 'FOUND',
                'data' => array(
                    'id' => $robot->id,
                    'name' => $robot->name,
                    'type' => $robot->type,
                    'year' => $robot->year
                )
            );
        }
    }
    if (count($data) > 0)
        echo json_encode($data);
    else
        echo json_encode(array('status' => 'NOT-FOUND'));


});

/**
 * @api {get} /robots/:id Request Robot information
 * @apiName GetRobot
 * @apiGroup Robot
 *
 * @apiParam {Number} id Robots unique ID.
 *
 * @apiUse RobotApiSuccessDesc
 *
 * @apiSuccessExample Success-Response:
 *     HTTP/1.1 200 OK
 *       {
 *               "id": "1",
 *               "name": "ASIMO",
 *               "type": "humanoid",
 *               "year": "2000"
 *       }
 *
 * @apiError RobotNotFound The id of the Robot was not found.
 *
 * @apiErrorExample Error-Response:
 *     HTTP/1.1 404 Not Found
 *
 */
$app->get('/robots/{id:[0-9]+}', function ($id) use ($app) {
    $phql = "SELECT * FROM Robots WHERE id = :id:";
    $robot = $app->modelsManager->executeQuery($phql, array(
        'id' => $id
    ))->getFirst();

    //Create a response
    $response = new Response();

    if (empty($robot->id)) {
        $response->setStatusCode(404, "Not Found");
    } else {
        $response->setJsonContent(array(
                'id' => $robot->id,
                'name' => $robot->name,
                'type' => $robot->type,
                'year' => $robot->year
            )
        );
    }

    return $response;
});

/**
 * @api {post} /robots/add/ Create Robot
 * @apiName CreateRobot
 * @apiGroup Robot
 *
 * @apiParamExample {json} Request-Example:
 *       {
 *           "name":"C-3PO",
 *           "type":"droid",
 *           "year":"1977"
 *       }
 *
 *
 * @apiUse RobotApiSuccessDesc
 *
 * @apiSuccessExample Success-Response:
 *     HTTP/1.1 200 OK
 *       {
 *           "status":"OK",
 *           "data":
 *               {
 *                  "name":"C-3PO5",
 *                  "type":"droid",
 *                  "year":1978,
 *                  "id":"2205"
 *              }
 *       }
 *
 * @apiError WrongRequest Wrong request format.
 *
 * @apiErrorExample Error-Response:
 *     HTTP/1.1 409 Conflict
 *        {
 *          "status":"ERROR",
 *          "messages":["name is required","type is required","year is required"]
 *        }
 *
 */
$app->post('/robots/add', function () use ($app) {

    $robot = $app->request->getJsonRawBody();

    $phql = "INSERT INTO Robots (name, type, year) VALUES (:name:, :type:, :year:)";

    $status = $app->modelsManager->executeQuery($phql, array(
        'name' => $robot->name,
        'type' => $robot->type,
        'year' => $robot->year
    ));

    //Create a response
    $response = new Response();

    //Check if the insertion was successful
    if ($status->success() == true) {

        //Change the HTTP status
        $response->setStatusCode(201, "Created");

        $robot->id = $status->getModel()->id;

        $response->setJsonContent(array('status' => 'OK', 'data' => $robot));

    } else {

        //Change the HTTP status
        $response->setStatusCode(409, "Conflict");

        //Send errors to the client
        $errors = array();
        foreach ($status->getMessages() as $message) {
            $errors[] = $message->getMessage();
        }

        $response->setJsonContent(array('status' => 'ERROR', 'messages' => $errors));
    }

    return $response;
});


/**
 * @api {post} /robots/update/:id Update Robot
 * @apiName UpdateRobot
 * @apiGroup Robot
 *
 * @apiParam {Number} id Robots unique ID.
 *
 * @apiParamExample {json} Request-Example:
 *       {
 *           "name":"C-3PO",
 *           "type":"droid",
 *           "year":"1977"
 *       }
 *
 *
 * @apiUse RobotApiSuccessDesc
 *
 * @apiSuccessExample Success-Response:
 *     HTTP/1.1 200 OK
 *       {
 *               "status": "OK"
 *       }
 *
 * @apiError WrongRequest Wrong request format.
 *
 * @apiErrorExample Error-Response:
 *     HTTP/1.1 409 Conflict
 *        {
 *          "status":"ERROR",
 *          "messages":["name is required","type is required","year is required"]
 *        }
 *
 */
$app->post('/robots/update/{id:[0-9]+}', function ($id) use ($app) {

    $robot = $app->request->getJsonRawBody();

    $phql = "UPDATE Robots SET name = :name:, type = :type:, year = :year: WHERE id = :id:";
    $status = $app->modelsManager->executeQuery($phql, array(
        'id' => $id,
        'name' => $robot->name,
        'type' => $robot->type,
        'year' => $robot->year
    ));

    //Create a response
    $response = new Response();

    //Check if the insertion was successful
    if ($status->success() == true) {
        $response->setJsonContent(array('status' => 'OK'));
    } else {

        //Change the HTTP status
        $response->setStatusCode(409, "Conflict");

        $errors = array();
        foreach ($status->getMessages() as $message) {
            $errors[] = $message->getMessage();
        }

        $response->setJsonContent(array('status' => 'ERROR', 'messages' => $errors));
    }

    return $response;
});


/**
 * @api {get} /robots/delete/:id Delete Robot
 * @apiName DeleteRobot
 * @apiGroup Robot
 *
 * @apiParam {Number} id Robots unique ID.
 *
 *
 * @apiSuccessExample Success-Response:
 *     HTTP/1.1 200 OK
 *
 * @apiError WrongRequest Robot not exists.
 *
 * @apiErrorExample Error-Response:
 *     HTTP/1.1 404 Not Found
 *
 */
$app->get('/robots/delete/{id:[0-9]+}', function ($id) use ($app) {

    $phql = "DELETE FROM Robots WHERE id = :id:";
    $status = $app->modelsManager->executeQuery($phql, array(
        'id' => $id
    ));

    //Create a response
    $response = new Response();

    if ($status->success() == true) {
        $response->setJsonContent(array('status' => 'OK'));
    } else {
        $response->setStatusCode(404, "Not Found");
    }

    return $response;
});

$app->notFound(function () use ($app) {
    $response = new Response();
    $response->setStatusCode(404, "Not Found");
    $response->setJsonContent(array('status' => 'ERROR', 'message' => 'Not found'));
    return $response;
});

$app->handle();

?>
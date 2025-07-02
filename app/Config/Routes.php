<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'AuthController::index');
$routes->get('(:segment)', 'AuthController::view/$1');

$routes->group('api', function($routes) {
    //Auth
    $routes->post('auth/register_admin', 'AuthController::registerAdmin');
    $routes->post('auth/login', 'AuthController::login');
    $routes->post('auth/logout', 'AuthController::logout');
    $routes->post('auth/refresh_token', 'AuthController::refreshToken');

    //Table
    $routes->get('table/list', 'TableController::list');
    $routes->get('table/info/(:num)', 'TableController::info/$1');
    $routes->post('table/create', 'TableController::create');
    $routes->put('table/update/(:num)', 'TableController::update/$1');
    $routes->delete('table/delete/(:num)', 'TableController::delete/$1');

    //Menu Category
    $routes->get('category/list', 'MenuCategoryController::list');
    $routes->get('category/info/(:num)', 'MenuCategoryController::info/$1');
    $routes->post('category/create', 'MenuCategoryController::create');
    $routes->put('category/update/(:num)', 'MenuCategoryController::update/$1');
    $routes->delete('category/delete/(:num)', 'MenuCategoryController::delete/$1');
    $routes->put('category/order_down/(:num)', 'MenuCategoryController::orderDown/$1');
    $routes->put('category/order_up/(:num)', 'MenuCategoryController::orderUp/$1');

    //Menu
    $routes->get('menu/list', 'MenuController::list');
    $routes->get('menu/info/(:num)', 'MenuController::info/$1');
    $routes->post('menu/create', 'MenuController::create');
    $routes->post('menu/update/(:num)', 'MenuController::update/$1');
    $routes->delete('menu/delete/(:num)', 'MenuController::delete/$1');
    $routes->put('menu/order_down/(:num)', 'MenuController::orderDown/$1');
    $routes->put('menu/order_up/(:num)', 'MenuController::orderUp/$1');
    $routes->get('menu/menus_order', 'MenuController::menusForOrder');

    //Order
    $routes->get('order', 'OrderController::orderList');
    $routes->post('order/table/(:num)', 'OrderController::tableOrderList/$1');
    $routes->post('order/create', 'OrderController::order');
    $routes->post('order/update', 'OrderController::orderMenuModify');
    $routes->delete('order/delete/(:num)', 'OrderController::tableOrderCancel/$1');
    $routes->post('order/move', 'OrderController::orderMove');

    //Payment
    $routes->put('payment/(:num)', 'PaymentController::payment/$1');
    $routes->put('payment/cancel/(:num)', 'PaymentController::paymentCancel/$1');

    //Sales
    $routes->get('sales', 'SalesController::salesInfo');
    $routes->get('sales/order/(:num)', 'SalesController::orderDetail/$1');
    $routes->get('sales/menu', 'SalesController::menuSales');
});

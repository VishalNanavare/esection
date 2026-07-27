<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

$routes->get('/', 'Auth::login');

// Authentication Routes
$routes->get('auth/login', 'Auth::login');
$routes->post('auth/processLogin', 'Auth::processLogin');
$routes->get('auth/logout', 'Auth::logout');

// Protected Application Routes
$routes->group('', ['filter' => 'authFilter'], function ($routes) {
    $routes->get('dashboard', 'Dashboard::index');

    // Centralized Select2 API Endpoints
    $routes->get('api/colleges', 'Api::colleges');
    $routes->get('api/states', 'Api::states');
    $routes->get('api/streams', 'Api::streams');
    
    // Student Form & Verification
    $routes->get('students/new', 'Students::newForm');
    $routes->get('students/getCollegeInfo/(:num)', 'Students::getCollegeInfo/$1');
    $routes->post('students/storeBatch', 'Students::storeBatch');
    
    // Universities Directory
    $routes->get('universities', 'Universities::index');
    $routes->post('universities/store', 'Universities::store');
    $routes->get('universities/getJson/(:num)', 'Universities::getJson/$1');
    $routes->post('universities/update/(:num)', 'Universities::update/$1');
    // POST only: a destructive action on a GET route is prefetchable by the
    // browser, crawlable, and triggerable by any <img src> on the page.
    $routes->post('universities/delete/(:num)', 'Universities::delete/$1');
    
    // Confirmations
    $routes->get('confirmations', 'Confirmations::index');
    $routes->post('confirmations/store', 'Confirmations::store');

    // Regularization
    $routes->get('regularization', 'Regularization::index');
    $routes->post('regularization/generateLetter', 'Regularization::generateLetter');

    // Reminders
    $routes->get('reminders/university', 'Reminders::university');
    $routes->post('reminders/university', 'Reminders::university');
    $routes->post('reminders/generateUniversityReminder', 'Reminders::generateUniversityReminder');
    $routes->get('reminders/student', 'Reminders::student');
    $routes->post('reminders/generateStudentReminder', 'Reminders::generateStudentReminder');
    
    // PDF Letter Dispatches
    $routes->get('pdf/dispatch/(:segment)', 'PdfController::dispatchLetter/$1');
});

<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

namespace CloudObjects\PhpMAE;

use Symfony\Component\HttpFoundation\Request;

class Sandbox {

    public static function initialize(Session $session, array $config) {
        if (!isset($session['sbhostname'])) {
            // Generate a hostname for the session
            $namespace = uniqid() . '.phpmae';
            $session['sbhostname'] = $namespace;

            // Write a default controller configuration
            $jsonLdConfig = [
                '@id' => 'coid://'.$namespace.'/SandboxController',
                '@type' => 'coid://phpmae.cloudobjects.io/ControllerClass'
            ];

            $path = $config['uploads_dir']
				.DIRECTORY_SEPARATOR.'config'
				.DIRECTORY_SEPARATOR.$namespace
				.DIRECTORY_SEPARATOR.'SandboxController';

            if (!is_dir($path)) mkdir($path, 0777, true);
            file_put_contents($path.DIRECTORY_SEPARATOR.'object.jsonld', json_encode($jsonLdConfig));

            return true;
        } else {
            // Already initialized
            return false;
        }
    }

    public static function isAuthenticated(Request $request, $namespace, $config) {
        if (!$request->cookies->has('session'))
            return false;
        
        $session = Session::createFromRequestWithKey($request, $config['sandbox.session_key']);
		return (isset($session['sbhostname']) && $session['sbhostname'] == $namespace);
    }

}
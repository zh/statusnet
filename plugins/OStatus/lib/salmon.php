<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
 *
 * A sample module to show best practices for StatusNet plugins
 *
 * PHP version 5
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @package   StatusNet
 * @author    James Walker <james@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class Salmon
{

    const NS_REPLIES = "http://salmon-protocol.org/ns/salmon-replies";

    const NS_MENTIONS = "http://salmon-protocol.org/ns/salmon-mention";
    
    /**
     * Sign and post the given Atom entry as a Salmon message.
     *
     * @fixme pass through the actor for signing?
     *
     * @param string $endpoint_uri
     * @param string $xml
     * @return boolean success
     */
    public function post($endpoint_uri, $xml)
    {
        if (empty($endpoint_uri)) {
            return false;
        }

        if (!common_config('ostatus', 'skip_signatures')) {
            $xml = $this->createMagicEnv($xml);
        }

        $headers = array('Content-Type: application/atom+xml');

        try {
            $client = new HTTPClient();
            $client->setBody($xml);
            $response = $client->post($endpoint_uri, $headers);
        } catch (HTTP_Request2_Exception $e) {
            common_log(LOG_ERR, "Salmon post to $endpoint_uri failed: " . $e->getMessage());
            return false;
        }
        if ($response->getStatus() != 200) {
            common_log(LOG_ERR, "Salmon at $endpoint_uri returned status " .
                $response->getStatus() . ': ' . $response->getBody());
            return false;
        }
        return true;
    }

    public function createMagicEnv($text)
    {
        $magic_env = new MagicEnvelope();

        // TODO: Should probably be getting the signer uri as an argument?
        $signer_uri = $magic_env->getAuthor($text);

        try {
            $env = $magic_env->signMessage($text, 'application/atom+xml', $signer_uri);
        } catch (Exception $e) {
            common_log(LOG_ERR, "Salmon signing failed: ". $e->getMessage());
            return $text;
        }
        return $magic_env->unfold($env);
    }


    public function verifyMagicEnv($dom)
    {
        $magic_env = new MagicEnvelope();
        
        $env = $magic_env->fromDom($dom);

        return $magic_env->verify($env);
    }
}

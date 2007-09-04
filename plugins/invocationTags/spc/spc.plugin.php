<?php

/*
+---------------------------------------------------------------------------+
| Openads v2.5                                                              |
| ============                                                              |
|                                                                           |
| Copyright (c) 2003-2007 Openads Limited                                   |
| For contact details, see: http://www.openads.org/                         |
|                                                                           |
| This program is free software; you can redistribute it and/or modify      |
| it under the terms of the GNU General Public License as published by      |
| the Free Software Foundation; either version 2 of the License, or         |
| (at your option) any later version.                                       |
|                                                                           |
| This program is distributed in the hope that it will be useful,           |
| but WITHOUT ANY WARRANTY; without even the implied warranty of            |
| MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the             |
| GNU General Public License for more details.                              |
|                                                                           |
| You should have received a copy of the GNU General Public License         |
| along with this program; if not, write to the Free Software               |
| Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA |
+---------------------------------------------------------------------------+
$Id$
*/

/**
 * @package    MaxPlugins
 * @subpackage InvocationTags
 * @author     Chris Nutting <chris@m3.net>
 *
 */

require_once MAX_PATH . '/plugins/invocationTags/InvocationTags.php';
require_once MAX_PATH . '/lib/max/Plugin/Translation.php';
require_once MAX_PATH . '/lib/max/Delivery/common.php';

/**
 *
 * Invocation tag plugin.
 *
 */
class Plugins_InvocationTags_Spc_Spc extends Plugins_InvocationTags
{
    /**
     * Set default values for options used by this plugin
     *
     * @var array Array of $key => $defaultValue
     */
    var $defaultOptionValues = array(
        'block' => 0,
        'blockcampaign' => 0,
        'target' => '',
        'source' => '',
        'withtext' => 0,
        'noscript' => 1,
    );

    /**
     * Make this the default publisher plugin
     *
     * @var boolean
     */
    var $default = true;

    /**
     * Constructor
     *
     */
    function Plugins_InvocationTags_Spc_Spc() {
        $this->publisherPlugin = true;
    }

     /**
     * Return name of plugin
     *
     * @return string
     */
    function getName()
    {
        return MAX_Plugin_Translation::translate('Publisher code - Single Page Call', $this->module, $this->package);
    }

    /**
     * Check if plugin is allowed
     *
     * @return boolean  True - allowed, false - not allowed
     */
    function isAllowed()
    {
        return false;
    }

    /**
     * Return invocation code for this plugin (codetype)
     *
     * @return string
     */
    function generateInvocationCode()
    {
        $conf = $GLOBALS['_MAX']['CONF'];
        $pref = $GLOBALS['_MAX']['CONF'];

        $mi = &$this->maxInvocation;

        // Get the affiliate information
        $doAffiliates = OA_Dal::factoryDO('affiliates');
        if ($doAffiliates->get($mi->affiliateid)) {
            $affiliate = $doAffiliates->toArray();
        }
        $doZones = OA_Dal::factoryDO('zones');
        $doZones->affiliateid = $mi->affiliateid;
        $doZones->find();
        while ($doZones->fetch() && $row = $doZones->toArray()) {
            $row['n'] = $affiliate['mnemonic'] . substr(md5(uniqid('', 1)), 0, 7);
            $aZones[] = $row;
        }

        if(count($aZones) == 0) {
            return 'No Zones Available!';
        }

        $additionalParams = "";
        foreach ($this->defaultOptionValues as $feature => $default) {
            // Skip source here since it's dealt with earlier
            if ($feature == 'source' || $feature == 'noscript') { continue; }
            if ($mi->$feature != $this->defaultOptionValues[$feature]) {
                $additionalParams .= "&amp;{$feature}=" . $mi->$feature;
            }
        }

        $varprefix = $conf['var']['prefix'];
        $name = (!empty($GLOBALS['_MAX']['PREF']['name'])) ? $GLOBALS['_MAX']['PREF']['name'] : MAX_PRODUCT_NAME;
        $channel = (!empty($mi->source)) ? $mi->source : $affiliate['mnemonic'] . "/test/preview";
        $script = "<?xml version='1.0' encoding='UTF-8' ?><!DOCTYPE html PUBLIC '-//W3C//DTD XHTML 1.0 Transitional//EN' 'http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd'>
<html xmlns='http://www.w3.org/1999/xhtml' xml:lang='en' lang='en'>
<head>
    <title>{$affiliate['name']} - Single Page Call (SPC) tags - Test page</title>";
        if ($mi->comments) {
            $search = array("{affiliate['mnemonic']}");
            $replace = array($affiliate['mnemonic']);
            $script .= "\n    <!--/*\n";
            $script .= str_replace($search, $replace, MAX_Plugin_Translation::translate('SPC Header script comment', $this->module, $this->package));
            $script .= "\n    */-->";
        }
        if ($mi->source) {
            $script .= "\n    <script type='text/javascript'><!--// <![CDATA[\n        var {$varprefix}source = '{$mi->source}';\n    // ]]> --></script>";
        }
        $script .= "\n    <script type='text/javascript' src='" . MAX_commonConstructDeliveryUrl($conf['file']['spcjs']) . "?id={$mi->affiliateid}{$additionalParams}'></script>
</head>

<body><div id='body'>";

        if ($mi->comments) {
            $script .= "\n    <!--/*\n" . MAX_Plugin_Translation::translate('SPC codeblock comment', $this->module, $this->package) . "\n    */-->";
        }

        foreach($aZones as $zone) {
            $name = $zone['zonename'] . ' ' . (($zone['width'] > -1) ? $zone['width'] : '*') . 'x' . (($zone['height'] > -1) ? $zone['height'] : '*');
            $script .= "<hr />{$name}<hr />\n";

            $codeblock = "<script type='text/javascript'><!--// <![CDATA[";
            $js_func = $varprefix . (($zone['delivery'] == phpAds_ZonePopup) ? 'showpop' : 'show');
            if ($mi->comments) {
                $codeblock .= "\n    /* {$name} */";
            }
            $codeblock .= "\n    {$js_func}({$zone['zoneid']});\n// ]]> --></script>";
            if ($zone['delivery'] != phpAds_ZoneText && $mi->noscript) {
                $codeblock .= "<noscript><a target='_blank' href='".MAX_commonConstructDeliveryUrl($conf['file']['click'])."?n={$zone['n']}'>";
                $codeblock .= "<img border='0' alt='' src='".MAX_commonConstructDeliveryUrl($conf['file']['view'])."?zoneid={$zone['zoneid']}&amp;n={$zone['n']}' /></a>";
                $codeblock .= "</noscript>";
            }

            $script .= "\n\n" . $codeblock;

            $script .=  "<textarea id='code_{$zone['zoneid']}' rows='10' cols='80'>" . htmlspecialchars($codeblock) . "</textarea>";
        }
            $script .= "
</div></body>
</html>";

        return $script;
    }

    /**
     * Return list of options
     *
     * @return array    Group of options
     */
    function getOptionsList()
    {
        // Publisher Invocation doesn't require a lot of the default options...
        if (is_array($this->defaultOptions)) {
            // JS code generates it's own cacheBuster
            unset($this->defaultOptions['cacheBuster']);
            // Publisher invocation is not designed for loading into another adserver
            unset($this->defaultOptions['3thirdPartyServer']);
        }
        $options = array (
            'spacer'        => MAX_PLUGINS_INVOCATION_TAGS_STANDARD,
            'block'         => MAX_PLUGINS_INVOCATION_TAGS_STANDARD,
            'blockcampaign' => MAX_PLUGINS_INVOCATION_TAGS_STANDARD,
            'spacer'        => MAX_PLUGINS_INVOCATION_TAGS_STANDARD,
            'target'        => MAX_PLUGINS_INVOCATION_TAGS_STANDARD,
            'source'        => MAX_PLUGINS_INVOCATION_TAGS_STANDARD,
            'withtext'      => MAX_PLUGINS_INVOCATION_TAGS_STANDARD,
            'noscript'      => MAX_PLUGINS_INVOCATION_TAGS_CUSTOM,
        );

        return $options;
    }

    function noscript()
    {
        $maxInvocation = &$this->maxInvocation;
        $noscript = (isset($maxInvocation->noscript)) ? $maxInvocation->noscript : 1;

        $option = '';
        $option .= "<td colspan='2'><img src='images/break-l.gif' height='1' width='200' vspace='6'></td></tr>";
        $option .= "<tr><td width='30'>&nbsp;</td>";
        $option .= "<td width='200'>Include &lt;noscript&gt; tags</td>";
        $option .= "<td width='370'><input type='radio' name='noscript' value='1'".($noscript == 1 ? " checked='checked'" : '')." tabindex='".($maxInvocation->tabindex++)."'>&nbsp;".$GLOBALS['strYes']."<br />";
        $option .= "<input type='radio' name='noscript' value='0'".($noscript == 0 ? " checked='checked'" : '')." tabindex='".($maxInvocation->tabindex++)."'>&nbsp;".$GLOBALS['strNo']."</td>";
        $option .= "</tr>";
        $option .= "<tr><td width='30'><img src='images/spacer.gif' height='1' width='100%'></td>";
        return $option;
    }
}

?>

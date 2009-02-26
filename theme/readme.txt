/** Howto: create a laconica theme
 *
 * @package   Laconica
 * @author Sarven Capadisli <csarven@controlyourself.ca>
 * @copyright 2009 Control Yourself, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://laconi.ca/
 */

Location of key paths and files under theme/:

./base/css/
./base/css/display.css
./base/images/

./default/css/
./default/css/display.css
./default/images/

./base/display.css contains layout, typography rules:
Only alter this file if you want to change the layout of the site. Please note that, any updates to this in future laconica releases may not be compatible with your version.

./default/css/display.css contains only the background images and colour rules:
This file is a good basis for creating your own theme.


1. Copy over the default theme to start off (replace 'mytheme'):
cp -r ./default ./mytheme

2. Edit your mytheme stylesheet:
nano ./mytheme/css/display.css

3. Search and replace a colour or a path to the background image of your choice.

4. Set /config.php to load 'mytheme':
$config['site']['theme'] = 'mytheme';

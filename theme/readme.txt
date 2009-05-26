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

Let's create a theme:

1. To start off, copy over the default theme:
cp -r default mytheme

2. Edit your mytheme stylesheet:
nano mytheme/css/display.css

a) Search and replace your colours and background images, or
b) Create your own layout either importing a separate stylesheet (e.g., change to @import url(base.css);) or simply place it before the rest of the rules.

4. Set /config.php to load 'mytheme':
$config['site']['theme'] = 'mytheme';

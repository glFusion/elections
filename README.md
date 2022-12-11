# Elections Plugin
The Elections plugin allows elections to be run on your site.

This plugin is largely based on the original Polls plugin, with a few
additional features improvements. See CHANGELOG.md for more information.

While anonymous votes are still handled similarly to the Polls plugin,
the best use of this plugin is for elections involving logged-in users.
Anonymous vote tracking is by cookie and IP address which may limit
responses from households using a shared computer or potentially from
companies behind NAT firewalls.

Vote data is encrypted using `COM_encyrpt()` and a unique encyrption key,
which is shown only to the voter, to ensure that even the site administrator
cannot determine how a user voted. (However, when the first vote is cast, an
administrator can infer the user's voting from the results. Once a second
vote is cast that is no longer possible.)

This plugin uses `gettext` to handle language strings, offering better handling
of plurals and a more standard translation process. As gettext support in
Windows is spotty, a full PHP implementation of gettext is supplied.
See https://launchpad.net/php-gettext

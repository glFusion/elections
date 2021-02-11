# Elections Plugin
The Elections plugin allows elections and polls to be run on your site.

This plugin is largely based on the original Polls plugin, with a few additional features or improvements:
  * The creation date is no longer changed each time a poll is saved.
  * Remove UNIX-style permissions, use simpler group for access to voting and results.
  * Add opening and closing date/time fields to control the voting window.
  * Get vote and question counts directly from the related tables for accuracy.
  * After closing, highlight winning selection(s) in results display.
  * Voters can be allowed to verify their votes by entering a key.  * Voters can be allowed to view their vote to confirm accuracy. Voting data is encrypted and requires a key which is shown to the voter but never saved in the database.
  * If the access group is not "All Users", the logged-in user ID is authoritative. This allows multiple votes from a shared computer or IP address.


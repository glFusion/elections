# Elections plugin for glFusion - Changelog

## v0.1.0
Release TBD

These reflect the changes relative to the existing Polls plugin on which the
Elections plugin is based.

  * Refactor into object classes.
  * Fix updating creation date whenever a poll is saved.
  * Remove UNIX-style permissions, use group for access to voting and results.
  * Add opening and closing date/time fields to control the voting window.
  * Get vote and question counts directly from the related tables for accuracy.
  * After closing, highlight winning selection(s) in results display.
  * Voters can be allowed to verify their votes by entering a key.
  * Results are shown ordered by number of votes received.
  * Elections can highlight the winner, or simply show the relative scores.
  * Questions and/or answer options can be shown in random order.

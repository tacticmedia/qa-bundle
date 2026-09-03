# Reviewing a run

The journey writes `screenshots_dir` and the review reads it. The review also writes `review_dir`,
which contains the notes file and the crops that the brief refers to. When the two halves run on
different filesystems, bind-mount both directories from the host: `screenshots_dir` so that the
review can read the captures, and `review_dir` so that a review continues to exist after a rebuild
and the crops are readable from the checkout.

Start the application and open `/_dev/screenshots`. The page shows a group grid. Open a capture from
the grid, and use the keyboard:

| Key | Does |
| --- | --- |
| Left / Right | Moves to the previous / next capture in this group. |
| Up / Down | Moves to the same capture one group up / down the sidebar, or to the grid of that group when it does not contain this capture. |
| Escape | Cancels the selection, then removes the focus from the field, then returns to the group grid. |
| Cmd+Enter, Ctrl+Enter | Submits the note form the focus is in, whether a new note or one being edited. |

Both axes are circular. While a field has the focus, the arrow keys move the caret and not the
page.

The review stores coordinates in natural image pixels, so a note stays correct at each width the
page renders the screenshot at. It measures `y` from the top of the page, not from the top of the
viewport.

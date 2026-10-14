// The only script of the review page. It uses no file of the host build, so a production
// build of the host does not depend on this bundle.
import './turbo.js';
import './csrf_protection.js';
import { Application } from './stimulus.js';
import AnchorHighlightController from './anchor_highlight_controller.js';
import AnnotateController from './annotate_controller.js';
import ClipboardController from './clipboard_controller.js';
import ConfirmController from './confirm_controller.js';

const application = Application.start();

application.register('qa-anchor-highlight', AnchorHighlightController);
application.register('qa-annotate', AnnotateController);
application.register('qa-clipboard', ClipboardController);
application.register('qa-confirm', ConfirmController);

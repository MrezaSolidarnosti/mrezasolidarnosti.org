import {events as contentEditorEvents} from "../events.js";
import Translator from "../../Translator/Translator.js";

/**
 * The empty-text-block hint: "Type '/' for blocks or '//' for commands".
 *
 * The command trigger is configurable (`config.commandTrigger` / `commandMenu.setTrigger`), so
 * the `//` here isn't hardcoded — it's requested from the editor. A block only ever holds an
 * `eventEmitter`, and the emitter is synchronous, so it asks and reads the answer back off the
 * same object. Falls back to `//` if nothing answers (a block used outside a full editor).
 */
export function slashCommandPlaceholder(eventEmitter) {
    const request = {};
    if (eventEmitter) {
        eventEmitter.emit(contentEditorEvents.commandTriggerRequested, request);
    }
    const trigger = request.trigger || '//';
    // A template rather than an interpolated literal: the whole sentence has to reach
    // the catalogue as one string, with the trigger substituted after translation.
    return Translator.translate("Type '/' for blocks or '%s' for commands").replace('%s', trigger);
}

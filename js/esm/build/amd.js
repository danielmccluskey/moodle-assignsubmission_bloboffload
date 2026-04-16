/*
 * This file is part of Moodle - http://moodle.org/
 *
 * @copyright  2026 Daniel McCluskey
 * @author     Daniel McCluskey
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const requireAmd = (modules) =>
    new Promise((resolve, reject) => {
        if (typeof window.require !== "function") {
            reject(new Error("Moodle AMD loader is unavailable."));
            return;
        }

        window.require(
            modules,
            (...results) => resolve(results),
            reject
        );
    });

export {requireAmd};

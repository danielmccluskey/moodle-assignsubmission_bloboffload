/*
 * This file is part of Moodle - http://moodle.org/
 *
 * @copyright  2026 Daniel McCluskey
 * @author     Daniel McCluskey
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare global {
    interface Window {
        require?: (
            modules: string[],
            onLoad: (...results: unknown[]) => void,
            onError?: (error: unknown) => void
        ) => void;
    }
}

export const requireAmd = <T extends unknown[]>(modules: string[]): Promise<T> =>
    new Promise((resolve, reject) => {
        if (typeof window.require !== "function") {
            reject(new Error("Moodle AMD loader is unavailable."));
            return;
        }

        window.require(
            modules,
            (...results: unknown[]) => resolve(results as T),
            reject
        );
    });

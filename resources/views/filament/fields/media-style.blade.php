{{--
    Styles for the media picker and the media grid.

    The panel has no custom Filament theme, so AdminPanelProvider injects this file
    into every page head through the HEAD_END render hook. Colours use Filament's CSS
    variables (--gray-*, --primary-*) so they follow the panel colours and dark mode.
    inset-inline-end keeps the remove and check badges on the correct side in RTL.

    Extending:
    - Keep the dm- prefix on new classes so they cannot clash with Filament or Tailwind classes.
--}}
<style>
    /* Picker: thumbnails, remove badge, and the add or change box. */
    .dm-picker { display: flex; flex-wrap: wrap; gap: .75rem; align-items: stretch; }
    .dm-picker-list { display: contents; }
    .dm-picker-item { position: relative; width: 8rem; height: 8rem; border-radius: .75rem; overflow: hidden; border: 1px solid var(--gray-200); background: var(--gray-50); }
    .dm-picker-single .dm-picker-item { width: 12rem; height: 12rem; }
    .dm-picker-round .dm-picker-item { width: 8rem; height: 8rem; border-radius: 9999px; }
    .dm-picker-item[x-sortable-handle] { cursor: grab; }
    .dm-picker-item img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .dm-picker-remove { position: absolute; top: .35rem; inset-inline-end: .35rem; border-radius: 9999px; background: rgb(255 255 255 / .9); box-shadow: 0 1px 3px rgb(0 0 0 / .2); }
    .dm-picker-round .dm-picker-remove { top: .6rem; inset-inline-end: .6rem; }
    .dm-picker-box, .dm-picker-change { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: .5rem; border: 2px dashed var(--gray-300); border-radius: .75rem; color: var(--gray-500); font-size: .875rem; cursor: pointer; transition: border-color .15s, color .15s, background-color .15s; }
    .dm-picker-box { flex: 1 1 100%; min-height: 8rem; padding: 1rem; }
    .dm-picker-list + .dm-picker-box { flex: 0 0 8rem; width: 8rem; height: 8rem; min-height: 0; text-align: center; }
    .dm-picker-change { width: 8rem; padding: .5rem; }
    .dm-picker-box:hover, .dm-picker-change:hover { border-color: var(--primary-600); color: var(--primary-600); background: var(--primary-50); }
    .dm-picker-box svg, .dm-picker-change svg { width: 1.75rem; height: 1.75rem; }
    .dm-picker-box:disabled, .dm-picker-change:disabled { opacity: .6; cursor: wait; }

    /* Grid: search bar, tiles, the check badge on chosen tiles, and the empty message. */
    .dm-grid-bar { display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem; }
    .dm-grid-search { flex: 1; max-width: 22rem; border-radius: .5rem; border: 1px solid var(--gray-300); padding: .5rem .75rem; font-size: .875rem; background: transparent; color: inherit; }
    .dm-grid-count { font-size: .875rem; color: var(--primary-600); }
    .dm-grid-tiles { display: grid; grid-template-columns: repeat(auto-fill, minmax(8.5rem, 1fr)); gap: .75rem; max-height: 60vh; overflow-y: auto; padding: .25rem; }
    .dm-grid-tile { position: relative; aspect-ratio: 1; border-radius: .75rem; overflow: hidden; border: 2px solid transparent; background: var(--gray-100); cursor: pointer; padding: 0; }
    .dm-grid-tile img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .dm-grid-tile:hover { border-color: var(--gray-400); }
    .dm-grid-tile-on, .dm-grid-tile-on:hover { border-color: var(--primary-600); box-shadow: 0 0 0 2px var(--primary-600); }
    .dm-grid-check { position: absolute; top: .4rem; inset-inline-end: .4rem; display: none; width: 1.5rem; height: 1.5rem; border-radius: 9999px; background: var(--primary-600); color: #fff; align-items: center; justify-content: center; }
    .dm-grid-check svg { width: 1rem; height: 1rem; }
    .dm-grid-tile-on .dm-grid-check { display: flex; }
    .dm-grid-name { position: absolute; inset-inline: 0; bottom: 0; padding: .25rem .5rem; font-size: .75rem; color: #fff; background: linear-gradient(transparent, rgb(0 0 0 / .65)); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; text-align: start; }
    .dm-grid-empty { padding: 2rem 1rem; text-align: center; color: var(--gray-500); font-size: .875rem; }

    /* Dark mode: Filament puts the dark class on the html element. */
    .dark .dm-picker-item { border-color: var(--gray-700); background: var(--gray-800); }
    .dark .dm-picker-remove { background: rgb(24 24 27 / .9); }
    .dark .dm-picker-box, .dark .dm-picker-change { border-color: var(--gray-600); color: var(--gray-400); }
    .dark .dm-picker-box:hover, .dark .dm-picker-change:hover { background: rgb(255 255 255 / .04); color: var(--primary-400); border-color: var(--primary-400); }
    .dark .dm-grid-search { border-color: var(--gray-600); }
    .dark .dm-grid-tile { background: var(--gray-800); }
    .dark .dm-grid-count { color: var(--primary-400); }
</style>

<style id="resavar-canonical-shell-layout">
:root{
    --az-admin-sidebar-width:278px;
    --az-admin-topbar-height:78px;
    --az-user-sidebar:278px;
}
html,
body.az-admin-body,
body.az-user-body{width:100%;max-width:100%;overflow-x:clip}

body.az-admin-body *,
body.az-admin-body *::before,
body.az-admin-body *::after,
body.az-user-body *,
body.az-user-body *::before,
body.az-user-body *::after{box-sizing:border-box}

.az-admin-body .material-symbols-outlined,
.az-user-body .material-symbols-outlined{
    font-family:'Material Symbols Outlined'!important;
    font-weight:normal!important;
    font-style:normal!important;
    line-height:1;
    letter-spacing:normal;
    text-transform:none;
    white-space:nowrap;
    word-wrap:normal;
    direction:ltr;
    font-feature-settings:'liga'!important;
    -webkit-font-feature-settings:'liga'!important;
    -webkit-font-smoothing:antialiased;
    visibility:visible!important;
    opacity:1!important;
}

/* Admin shell */
.az-admin-app{width:100%!important;max-width:100%!important;min-width:0!important}
.az-admin-main{
    width:calc(100% - var(--az-admin-sidebar-width))!important;
    max-width:calc(100vw - var(--az-admin-sidebar-width))!important;
    min-width:0!important;
    margin-left:var(--az-admin-sidebar-width)!important;
    padding-top:0!important;
    overflow-x:clip!important
}
.az-admin-topbar{width:100%!important;max-width:100%!important;min-width:0!important;left:auto!important;right:auto!important}
.az-admin-content{width:100%!important;max-width:100%!important;min-width:0!important;overflow-x:clip!important}
.az-admin-main>*,
.az-admin-topbar>*,
.az-admin-content>*,
.az-admin-content section,
.az-admin-content article,
.az-admin-content form,
.az-admin-content [class*="grid"],
.az-admin-content [class*="flex"]{min-width:0!important;max-width:100%}

.az-admin-table-wrap,
.az-s78-table-wrap,
.az-responsive-table-shell,
.az-admin-content .overflow-x-auto,
.az-admin-content .table-responsive,
.az-admin-content [class*="table-wrapper"]{
    display:block!important;
    width:100%!important;
    max-width:100%!important;
    min-width:0!important;
    overflow-x:auto!important;
    overflow-y:hidden;
    overscroll-behavior-x:contain;
    -webkit-overflow-scrolling:touch
}

@media(min-width:1101px){
    .az-admin-sidebar{transform:translateX(0)!important;visibility:visible!important}
    .az-sidebar-trigger,.az-sidebar-close,.az-admin-backdrop{display:none!important}
}
@media(max-width:1100px){
    .az-admin-sidebar{transform:translateX(-102%)!important}
    .az-admin-sidebar.is-open{transform:translateX(0)!important}
    .az-admin-main{width:100%!important;max-width:100%!important;margin-left:0!important}
    .az-sidebar-trigger,.az-sidebar-close{display:inline-grid!important}
}

/* Guest shell */
.az-user-shell{width:100%!important;max-width:100%!important;min-width:0!important;overflow-x:clip!important}
.az-user-main{
    width:calc(100% - var(--az-user-sidebar))!important;
    max-width:calc(100vw - var(--az-user-sidebar))!important;
    min-width:0!important;
    margin-left:var(--az-user-sidebar)!important;
    overflow-x:clip!important
}
.az-user-topbar,.az-user-content{width:100%!important;max-width:100%!important;min-width:0!important}
.az-user-content>*,
.az-user-content section,
.az-user-content article,
.az-user-content form,
.az-user-content [class*="grid"],
.az-user-content [class*="flex"]{min-width:0!important;max-width:100%}
.az-user-table-wrap,
.az-user-content .overflow-x-auto,
.az-user-content .table-responsive,
.az-user-content [class*="table-wrapper"]{
    display:block!important;
    width:100%!important;
    max-width:100%!important;
    min-width:0!important;
    overflow-x:auto!important;
    overflow-y:hidden;
    overscroll-behavior-x:contain;
    -webkit-overflow-scrolling:touch
}
@media(min-width:981px){
    .az-user-sidebar{display:flex!important}
    .az-user-mobile-drawer,.az-user-mobile-backdrop,.az-user-menu-button,.az-user-mobile-bottom{display:none!important}
}
@media(max-width:980px){
    .az-user-sidebar{display:none!important}
    .az-user-main{width:100%!important;max-width:100%!important;margin-left:0!important}
    .az-user-menu-button{display:inline-grid!important}
}
</style>

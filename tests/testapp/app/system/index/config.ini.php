;<?php die(''); ?>
;for security reasons , don't remove or modify the first line


[responses]

html=adminHtmlResponse
htmlauth=adminLoginHtmlResponse

[modules]


[coordplugins]
auth="index/auth.coord.ini.php"
jacl2=1

[coordplugin_jacl2]
on_error=2
error_message="jacl2~errors.action.right.needed"
on_error_action="jelix~error:badright"

[acl2]
driver=db
hiddenRights=
hideRights=off
authAdapterClass=jAcl2JAuthAdapter

[webassets_common]
master_admin.css[]="$jelix/design/master_admin.css"
jacl2_admin.require = jquery_ui
jacl2_admin.css[]="$jelix/design/jacl2.css"
jacl2_admin.js[]="$jelix/js/jacl2db_admin.js"

jauthdb_admin.require = jquery_ui
;jauthdb_admin.css[]="$jelix/design/jauthdb_admin.css"
jauthdb_admin.js[]="$jelix/js/authdb_admin.js"
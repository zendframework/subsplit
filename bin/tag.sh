#!/usr/bin/zsh
SCRIPT_PATH=$(readlink -f $0)
WORKDIR=$(dirname $SCRIPT_PATH)
WORKDIR=$(dirname $WORKDIR)

if [ $# -eq 0 ];then
    echo "No arguments supplied; must supply a tag" ;
    exit 2 ;
fi

if [ -z "$1" ];then
    echo "No arguments supplied; must supply a tag" ;
    exit 2 ;
fi

# Make sure previous run completed
if [ -d "./.subsplit/.git/subtree-cache" ];then
    echo "Previous run/update does not appear to have completed!" ;
    exit 2 ;
fi

TAG="$1"

(
    cd $WORKDIR && /usr/local/bin/git subsplit publish "
    library/Zend/Authentication:git@github.com:zendframework/Component_ZendAuthentication.git
    library/Zend/Barcode:git@github.com:zendframework/Component_ZendBarcode.git
    library/Zend/Cache:git@github.com:zendframework/Component_ZendCache.git
    library/Zend/Captcha:git@github.com:zendframework/Component_ZendCaptcha.git
    library/Zend/Code:git@github.com:zendframework/Component_ZendCode.git
    library/Zend/Config:git@github.com:zendframework/Component_ZendConfig.git
    library/Zend/Console:git@github.com:zendframework/Component_ZendConsole.git
    library/Zend/Crypt:git@github.com:zendframework/Component_ZendCrypt.git
    library/Zend/Db:git@github.com:zendframework/Component_ZendDb.git
    library/Zend/Debug:git@github.com:zendframework/Component_ZendDebug.git
    library/Zend/Di:git@github.com:zendframework/Component_ZendDi.git
    library/Zend/Dom:git@github.com:zendframework/Component_ZendDom.git
    library/Zend/Escaper:git@github.com:zendframework/Component_ZendEscaper.git
    library/Zend/EventManager:git@github.com:zendframework/Component_ZendEventManager.git
    library/Zend/Feed:git@github.com:zendframework/Component_ZendFeed.git
    library/Zend/File:git@github.com:zendframework/Component_ZendFile.git
    library/Zend/Filter:git@github.com:zendframework/Component_ZendFilter.git
    library/Zend/Form:git@github.com:zendframework/Component_ZendForm.git
    library/Zend/Http:git@github.com:zendframework/Component_ZendHttp.git
    library/Zend/I18n:git@github.com:zendframework/Component_ZendI18n.git
    library/Zend/InputFilter:git@github.com:zendframework/Component_ZendInputFilter.git
    library/Zend/Json:git@github.com:zendframework/Component_ZendJson.git
    library/Zend/Ldap:git@github.com:zendframework/Component_ZendLdap.git
    library/Zend/Loader:git@github.com:zendframework/Component_ZendLoader.git
    library/Zend/Log:git@github.com:zendframework/Component_ZendLog.git
    library/Zend/Mail:git@github.com:zendframework/Component_ZendMail.git
    library/Zend/Math:git@github.com:zendframework/Component_ZendMath.git
    library/Zend/Memory:git@github.com:zendframework/Component_ZendMemory.git
    library/Zend/Mime:git@github.com:zendframework/Component_ZendMime.git
    library/Zend/ModuleManager:git@github.com:zendframework/Component_ZendModuleManager.git
    library/Zend/Mvc:git@github.com:zendframework/Component_ZendMvc.git
    library/Zend/Navigation:git@github.com:zendframework/Component_ZendNavigation.git
    library/Zend/Paginator:git@github.com:zendframework/Component_ZendPaginator.git
    library/Zend/Permissions/Acl:git@github.com:zendframework/Component_ZendPermissionsAcl.git
    library/Zend/Permissions/Rbac:git@github.com:zendframework/Component_ZendPermissionsRbac.git
    library/Zend/ProgressBar:git@github.com:zendframework/Component_ZendProgressBar.git
    library/Zend/Serializer:git@github.com:zendframework/Component_ZendSerializer.git
    library/Zend/Server:git@github.com:zendframework/Component_ZendServer.git
    library/Zend/ServiceManager:git@github.com:zendframework/Component_ZendServiceManager.git
    library/Zend/Session:git@github.com:zendframework/Component_ZendSession.git
    library/Zend/Soap:git@github.com:zendframework/Component_ZendSoap.git
    library/Zend/Stdlib:git@github.com:zendframework/Component_ZendStdlib.git
    library/Zend/Tag:git@github.com:zendframework/Component_ZendTag.git
    library/Zend/Test:git@github.com:zendframework/Component_ZendTest.git
    library/Zend/Text:git@github.com:zendframework/Component_ZendText.git
    library/Zend/Uri:git@github.com:zendframework/Component_ZendUri.git
    library/Zend/Validator:git@github.com:zendframework/Component_ZendValidator.git
    library/Zend/Version:git@github.com:zendframework/Component_ZendVersion.git
    library/Zend/View:git@github.com:zendframework/Component_ZendView.git
    library/Zend/XmlRpc:git@github.com:zendframework/Component_ZendXmlRpc.git
" --update --no-heads --tags "release-$TAG" ;
)

# Remove subtree cache on completion
rm -rf "$WORKDIR/.subsplit/.git/subtree-cache"

/**
 * Auth module class
 * @class
 * @namespace
 * @extends {Colibri.Modules.Module}
 * @memberof App.Modules
 */
App.Modules.Auth = class extends Colibri.Modules.Module {

    /** 
     * Is module ready
     * @private
     * @var {boolean}
     */
    _ready = false;
    /**
     * Application token
     * @private
     * @var {string}
     */ 
    _app_token = null;

    /** @constructor */
    constructor() { 
        super('Auth');
        
    }

    /**
     * Initializes the module
     * @public
     * @param {boolean} useCookie - indicates using of authorization cookie in requests
     * @param {string} cookieName - change the authorization cookie name
     * @param {string} remoteDomain - remote domain for requests
     * @param {string} appToken - application token for requests
     * @param {string} useLanguage - language to use for requests
     * @returns {void} 
     */
    InitializeModule(useCookie = true, cookieName = 'ss-jwt', remoteDomain = null, appToken = null, useLanguage = null) {
        super.InitializeModule();
        console.log('Initializing module Auth'); 
        
        this._store = App.Store.AddChild('app.auth', {}, this); 
        this._store.AddPathLoader('auth.settings', () => this.Settings());


        this._app_token = appToken;
        this.useAuthorizationCookie = useCookie;
        this.authorizationCookieName = cookieName; 
        if(remoteDomain) {
            this.remoteDomain = remoteDomain;
        }

        this._session = new App.Modules.Auth.Session();
        this._app = new App.Modules.Auth.Application();
        this._members = new App.Modules.Auth.Members();

        this._ready = true;
        this._currentLang = useLanguage;

        this._store.AddHandler('StoreLoaderCrushed', (event, args) => {
            if(args.status === 403) {
                location.reload();
            }
        });
        this.AddHandler('CallError', (event, args) => {
            if(args.status === 403) {
                location.reload();
            }
        });

    }

    /**
     * Renders the module
     * @public
     */
    Render() {
        console.log('Rendering Module Auth');    
        
    }

    /**
     * Registers events for the module
     * @protected
     */
    RegisterEvents() {
        console.log('Registering module events for Auth');
    }

    /**
     * Registers event handlers for the module
     * @protected
     */
    RegisterEventHandlers() {
        console.log('Registering event handlers for Auth');
    }

    /**
     * Registers module event handlers
     * @var {Colibri.Storages.Store}
     */
    get Store() {
        return this._store;
    }

    /**
     * Indicates if module is ready
     * @var {boolean}
     */
    get IsReady() {
        return this._ready;
    }

    /**
     * Gets the current session
     * @var {App.Modules.Auth.Session}
     */
    get Session() {
        return this._session;
    }

    /**
     * Gets the members
     * @var {App.Modules.Auth.Members}
     */
    get Members() {
        return this._members;
    }

    /**
     * Gets the current application
     * @type {App.Modules.Auth.Application}
     */
    get App() {
        return this._app;
    }

    /**
     * Gets the application token
     * @type {string}
     */
    get appToken() {
        return this._app_token;
    }

    /**
     * Sets the language to use for requests
     * @type {string}
     */
    set useLanguage(value) {
        this._currentLang = value;
    }

    /**
     * Gets the language to use for requests
     * @type {string}
     */
    get useLanguage() {
        return this._currentLang;
    }

    /**
     * Get the module settings
     * @param {boolean} returnPromise - indicates if the method should return a promise
     * @returns {Promise} - returns a promise if returnPromise is true
     * @public 
     * @async
     */
    Settings(returnPromise = true) {
        const promise = this.Call('App', 'Settings', {}, {'X-AppToken': Auth.appToken, 'DeviceId': App.Device.id, 'Colibri-Language': Auth.useLanguage});
        if(returnPromise) {
            return promise;
        }
        promise.then((settings) => {
            Auth.Store.Set('auth.settings', settings.result);
            resolve(settings.result);
        }).catch(response => reject(response)); 
    }

    /**
     * Gets additional methods for data pages
     * @param {App.Modules.Sites.DataPage} dataPage - the data page
     * @param {object} storage - the storage object
     * @returns {Promise} - returns a promise with additional methods
     * @public
     * @async
     */
    DataPageAdditionalMethods(dataPage, storage) {
        return new Promise((resolve, reject) => {
            if (storage.name === 'members') {
                resolve({
                    contextmenu: [
                        { name: 'create-autologin', title: 'Создать Автовход', icon: Colibri.UI.MeasureIcon },
                        { name: 'change-password', title: 'Изменить пароль', icon: Colibri.UI.ChangePassIcon },
                    ]
                });
            } else {
                resolve({});
            }
        });

    }

    /**
     * Executes additional methods for data pages
     * @param {object} command - the command object
     * @param {object} storage - the storage object
     * @param {object} data - the data object
     * @returns {Promise} - returns a promise with the result of the execution
     * @public
     * @async
     */
    DataPageAdditionalExecuteMethod(command, storage, data) {
        return new Promise((resolve, reject) => {
            try {
                if (command.name === 'create-autologin') {

                    App.Prompt.Show('Создание ссылки для автологина', {
                        domain: {
                            component: 'Text',
                            desc: 'Введите адрес (только домен)',
                        },
                        app: {
                            component: 'Select',
                            desc: 'Выберите приложение',
                            lookup: {
                                storage: {
                                    module: 'Auth',
                                    name: 'applications',
                                    select: '*',
                                    title: 'key',
                                    value: 'token',
                                    controller: {
                                        module: 'Manage',
                                        class: 'Lookup',
                                        method: 'Get'
                                    }
                                }
                            },
                            selector: {
                                title: 'key',
                                value: 'token'
                            }
                        }
                    }, 'Создать').then(d => {
                        Auth.Members.RequestAutoLoginBackend(d.app, data.token, 'https://' + d.domain);
                    });
                    

                } else if (command.name === 'change-password') {

                    App.Prompt.Show('Изменение пароля', {
                        pass: {
                            component: 'Password',
                            desc: 'Введите новый пароль',
                        }
                    }, 'Изменить').then(d => {
                        Auth.Members.ChangePasswordBackend(data.token, d.pass).then(() => {
                            App.Notices.Add(new Colibri.UI.Notice('Пароль успешно изменен', Colibri.UI.Notice.Success));
                        }).catch((e) => {
                            App.Notices.Add(new Colibri.UI.Notice(e.result, Colibri.UI.Notice.Error));
                        });
                    });
                    

                } else {
                    resolve();
                }
            } catch (e) {
                resolve();
            }
        });
    }

}

const Auth = new App.Modules.Auth();

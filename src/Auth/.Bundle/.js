


App.Modules.Auth = class extends Colibri.Modules.Module {

    _ready = false;
    _app_token = null;

    /** @constructor */
    constructor() {
        super('Auth');
        
    }

    InitializeModule(useCookie = true, cookieName = 'ss-jwt', remoteDomain = null, appToken = null) {
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

    Render() {
        console.log('Rendering Module Auth');    
        
    }

    RegisterEvents() {
        console.log('Registering module events for Auth');
    }

    RegisterEventHandlers() {
        console.log('Registering event handlers for Auth');
    }

    get Store() {
        return this._store;
    }

    get IsReady() {
        return this._ready;
    }

    get Session() {
        return this._session;
    }
    get Members() {
        return this._members;
    }
    get App() {
        return this._app;
    }
    
    get appToken() {
        return this._app_token;
    }

    Settings(returnPromise = true) {
        const promise = this.Call('App', 'Settings', {}, {'X-AppToken': Auth.appToken});
        if(returnPromise) {
            return promise;
        }
        promise.then((settings) => {
            Auth.Store.Set('auth.settings', settings.result);
            resolve(settings.result);
        }).catch(response => reject(response));
    }

}

App.Modules.Auth.Session = class extends Colibri.IO.RpcRequest  {

    constructor() {
        super('Auth', Auth.requestType, Auth.remoteDomain);
    }

    Start() {
        return new Promise((resolve, reject) => {
            this.Call('Session', 'Start', {}, {'X-AppToken': Auth.appToken}).then((response) => {
                Auth.Store.Set('auth.session', response.result.session);
                resolve(response.result.session);
            }).catch(response => reject(response));
        });
    }

    Login(login, password, code) {
        return new Promise((resolve, reject) => {
            this.Call('Session', 'Login', {login: login, password: password, code: code}, {'X-AppToken': Auth.appToken}).then((response) => {
                Auth.Store.Set('auth.session', response.result.session);
                resolve(response.result.session);
            }).catch(response => {
                if(response.status === 206) { // 2-х факторка
                    resolve(null);
                }
                else {
                    reject(response);
                }
            });
        });
    }

    Logout() {
        return new Promise((resolve, reject) => {
            this.Call('Session', 'Logout', {}, {'X-AppToken': Auth.appToken}).then((response) => {
                resolve(response.result.session);
                Auth.Store.Set('auth.session', response.result.session);
            }).catch(response => reject(response));
        });
    }

    LogoutFromAll() {
        return new Promise((resolve, reject) => {
            this.Call('Session', 'LogoutFromAll', {}, {'X-AppToken': Auth.appToken}).then((response) => {
                Auth.Store.Set('auth.session', response.result.session);
                resolve(response.result.session);
            }).catch(response => reject(response));
        });
    }

}

App.Modules.Auth.Members = class extends Colibri.IO.RpcRequest  {

    constructor() {
        super('Auth', Auth.requestType, Auth.remoteDomain);
    }

    Register(email, email_confirmed, phone, phone_confirmed, password, confirmation, first_name = '', last_name = '', patronymic = '', gender = 'male', birthdate = null, invitation = null) {
        return new Promise((resolve, reject) => {
            this.Call('Member', 'Register', {
                email: email,
                email_confirmed: email_confirmed,
                phone: phone,
                phone_confirmed: phone_confirmed,
                password: password,
                confirmation: confirmation,
                first_name: first_name,
                last_name: last_name,
                patronymic: patronymic,
                gender: gender,
                birthdate: birthdate,
                invitation: invitation
            }, {'X-AppToken': Auth.appToken}).then((response) => {
                Auth.Store.Set('auth.session', response.result.session);
                resolve(response.result.session);
            }).catch(response => reject(response));
        });
    }

    GetInvite(code) {
        return new Promise((resolve, reject) => {
            this.Call('Invites', 'Get', {code: code}, {'X-AppToken': Auth.appToken}).then((response) => {
                resolve(response.result);
            }).catch(response => reject(response));
        });
    }

    Invite(email, fio, params) {
        return new Promise((resolve, reject) => {
            this.Call('Invites', 'Create', {
                email: email,
                fio: fio,
                params: params
            }, {'X-AppToken': Auth.appToken}).then((response) => {
                resolve(response.result);
            }).catch(response => reject(response));
        });
    }

    BeginConfirmationProcess(property = 'email', value = '') {
        return new Promise((resolve, reject) => {
            this.Call('Member', 'BeginConfirmationProcess', {property: property, value: value}, {'X-AppToken': Auth.appToken}).then((response) => {
                // Auth.Store.Set('auth.session', response.result.session);
                resolve(response.result.session);
            }).catch(response => reject(response));
        });
    }

    ConfirmProperty(code, property = 'email', value = '') {
        return new Promise((resolve, reject) => {
            this.Call('Member', 'ConfirmProperty', {property: property, code: code, value: value}, {'X-AppToken': Auth.appToken}).then((response) => {
                Auth.Store.Set('auth.session', response.result.session);
                resolve(response.result.session);
            }).catch(response => reject(response));
        });
    }

    BeginPasswordResetProcess(email, phone) {
        return new Promise((resolve, reject) => {
            this.Call('Member', 'BeginPasswordResetProcess', {email: email, phone: phone}, {'X-AppToken': Auth.appToken}).then((response) => {
                // Auth.Store.Set('auth.session', response.result.session);
                resolve(response.result.session);
            }).catch(response => reject(response));
        });
    }

    ResetPassword(email, phone, code, password, confirmation) {
        return new Promise((resolve, reject) => {
            this.Call('Member', 'ResetPassword', {email: email, phone: phone, code: code, password: password, confirmation: confirmation}, {'X-AppToken': Auth.appToken}).then((response) => {
                // Auth.Store.Set('auth.session', response.result.session);
                resolve(response.result.session);
            }).catch(response => reject(response));
        });
    }

    BeginIdentityUpdateProcess(value, property = 'email') {
        return new Promise((resolve, reject) => {
            this.Call('Member', 'BeginIdentityUpdateProcess', {property: property, value: value}, {'X-AppToken': Auth.appToken}).then((response) => {
                // Auth.Store.Set('auth.session', response.result.session);
                resolve(response.result.session);
            }).catch(response => reject(response));
        });
    }

    ChangeIdentity(code, value, property = 'email') {
        return new Promise((resolve, reject) => {
            this.Call('Member', 'ChangeIdentity', {property: property, code: code, value: value}, {'X-AppToken': Auth.appToken}).then((response) => {
                Auth.Store.Set('auth.session', response.result.session);
                resolve(response.result.session);
            }).catch(response => reject(response));
        });
    }

    SaveProfile(last_name, first_name, patronymic = null, birthdate = null, gender = null) {
        return new Promise((resolve, reject) => {
            this.Call('Member', 'UpdateProfile', {last_name: last_name, first_name: first_name, patronymic: patronymic, birthdate: birthdate, gender: gender}, {'X-AppToken': Auth.appToken}).then((response) => {
                Auth.Store.Set('auth.session', response.result.session);
                resolve(response.result.session);
            }).catch(response => reject(response));
        });
    }

    ChangePassword(original, password, confirmation) {
        return new Promise((resolve, reject) => {
            this.Call('Member', 'ChangePassword', {original: original, password: password, confirmation: confirmation}, {'X-AppToken': Auth.appToken}).then((response) => {
                // Auth.Store.Set('auth.session', response.result.session);
                resolve(response.result.session);
            }).catch(response => reject(response));
        });
    }

    BlockAccount() {
        return new Promise((resolve, reject) => {
            this.Call('Member', 'BlockAccount', {}, {'X-AppToken': Auth.appToken}).then((response) => {
                Auth.Store.Set('auth.session', response.result.session);
                resolve(response.result.session);
            }).catch(response => reject(response));
        });
    }

    ToggleTwoFactorAuth() {
        return new Promise((resolve, reject) => {
            this.Call('Member', 'ToggleTwoFactorAuth', {}, {'X-AppToken': Auth.appToken}).then((response) => {
                Auth.Store.Set('auth.session', response.result.session);
                resolve(response.result.session);
            }).catch(response => reject(response));
        });
    }

    RequestAutoLogin(memberToken, returnTo) {
        return new Promise((resolve, reject) => {
            this.Call('Member', 'RequestAutologin', {token: memberToken, return: returnTo}, {'X-AppToken': Auth.appToken}).then((response) => {
                Auth.Store.Set('auth.session', response.result.session);
                const w = new App.Modules.Auth.Components.AutologinRequest('autologin-window', document.body);
                w.Show(response.result.link);
                w.AddHandler('WindowClosed', (event, args) => {
                    w.Dispose();
                });
                resolve(response.result.session);
            }).catch(response => reject(response));
        });
    }

    _importPublicKey(publickey) {
        return crypto.subtle.importKey(
            "spki", 
            publickey.spkiPem2spkiDer(), 
            { name: "RSA-OAEP", hash: "SHA-256" }, 
            true, 
            ["encrypt"]
        );
    }

    Encrypt(message, member) {
        return new Promise((resolve, reject) => {
            this.Call('Member', 'EncryptMessage', {message: message, for: member}, {'X-AppToken': Auth.appToken}).then((response) => {
                resolve(response.result.encrypted);
            }).catch(response => reject(response));
        });
    }

    Decrypt(message) {
        return new Promise((resolve, reject) => {
            this.Call('Member', 'DecryptMessage', {message: message}, {'X-AppToken': Auth.appToken}).then((response) => {
                resolve(response.result.decrypted);
            }).catch(response => reject(response));
        });
    }

    Search(term) {
        return new Promise((resolve, reject) => {
            this.Call('Member', 'Search', {term: term}, {'X-AppToken': Auth.appToken}).then((response) => {
                resolve(response.result);
            }).catch(response => reject(response));
        });
    }

}

App.Modules.Auth.Application = class extends Colibri.IO.RpcRequest  {

    constructor() {
        super('Auth', Auth.requestType, Auth.remoteDomain);
    }

    Settings() {
        return new Promise((resolve, reject) => {
            this.Call('App', 'Settings', {}, {'X-AppToken': Auth.appToken}).then((settings) => {
                Auth.Store.Set('auth.settings', settings.result);
                resolve(settings.result);
            }).catch(response => reject(response));
        });
    }

}

App.Modules.Auth.Icons = {};
App.Modules.Auth.Icons.UserIcon = '<svg width="17" height="17" viewBox="0 0 17 17" fill="none" xmlns="http://www.w3.org/2000/svg"><g clip-path="url(#clip0_1406_472)"><path d="M8.3788 8.18895C9.50381 8.18895 10.478 7.78545 11.274 6.98936C12.0699 6.1934 12.4734 5.21948 12.4734 4.09434C12.4734 2.96959 12.0699 1.99555 11.2738 1.19933C10.4777 0.403494 9.50368 0 8.3788 0C7.25365 0 6.27974 0.403494 5.48377 1.19946C4.68781 1.99542 4.28418 2.96946 4.28418 4.09434C4.28418 5.21948 4.68781 6.19353 5.4839 6.98949C6.28 7.78532 7.25404 8.18895 8.3788 8.18895Z" fill="#C4C4C4"/><path d="M15.5433 13.0721C15.5204 12.7408 15.474 12.3795 15.4056 11.9979C15.3366 11.6135 15.2478 11.2501 15.1414 10.9179C15.0315 10.5746 14.8821 10.2356 14.6974 9.91068C14.5057 9.57346 14.2806 9.27982 14.0279 9.03819C13.7637 8.78541 13.4403 8.58217 13.0662 8.43392C12.6935 8.28645 12.2804 8.21175 11.8385 8.21175C11.6649 8.21175 11.4971 8.28295 11.173 8.49397C10.9735 8.62406 10.7402 8.77451 10.4797 8.94092C10.257 9.08281 9.95536 9.21575 9.58273 9.33611C9.21918 9.45375 8.85006 9.51341 8.48573 9.51341C8.12141 9.51341 7.75241 9.45375 7.38847 9.33611C7.01624 9.21588 6.71455 9.08294 6.49212 8.94104C6.23415 8.7762 6.00069 8.62575 5.79823 8.49384C5.47449 8.28282 5.30653 8.21162 5.133 8.21162C4.69098 8.21162 4.27802 8.28645 3.90539 8.43405C3.53159 8.58204 3.20799 8.78528 2.94353 9.03832C2.69101 9.28008 2.46572 9.57359 2.27428 9.91068C2.08972 10.2356 1.94031 10.5745 1.83032 10.9181C1.7241 11.2502 1.63525 11.6135 1.56625 11.9979C1.4979 12.379 1.45147 12.7405 1.42851 13.0725C1.40594 13.3978 1.39453 13.7354 1.39453 14.0764C1.39453 14.9638 1.67663 15.6822 2.23291 16.212C2.78232 16.7348 3.50928 17 4.39332 17H12.5789C13.463 17 14.1897 16.7349 14.7392 16.212C15.2956 15.6826 15.5777 14.964 15.5777 14.0762C15.5776 13.7337 15.566 13.3958 15.5433 13.0721Z" fill="#C4C4C4"/></g><defs><clipPath id="clip0_1406_472"><rect width="17" height="17" fill="white"/></clipPath></defs></svg>';
App.Modules.Auth.Icons.PasswordIcon = '<svg width="15" height="17" viewBox="0 0 15 17" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M7.36667 0C4.78543 0 2.67879 2.13905 2.67879 4.76V6.12H1.33939C0.599379 6.12 0 6.7286 0 7.48V15.64C0 16.3914 0.599379 17 1.33939 17H13.3939C14.134 17 14.7333 16.3914 14.7333 15.64V7.48C14.7333 6.7286 14.134 6.12 13.3939 6.12H12.0545V4.76C12.0545 2.22468 10.07 0.182624 7.60472 0.0491406C7.52898 0.0181631 7.44831 0.00150903 7.36667 0ZM7.36667 1.36C9.22418 1.36 10.7152 2.87391 10.7152 4.76V6.12H4.01818V4.76C4.01818 2.87391 5.50915 1.36 7.36667 1.36Z" fill="#C4C4C4"/></svg>';
App.Modules.Auth.Icons.Done = '<svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M18.5 4.15625L6.8125 15.8438L1.5 10.5312" stroke="black" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>';
const Auth = new App.Modules.Auth();

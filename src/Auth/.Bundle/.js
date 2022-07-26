


App.Modules.Auth = class extends Colibri.Modules.Module {

    /** @constructor */
    constructor() {
        super('Auth');
        
    }

    InitializeModule() {
        super.InitializeModule();
        console.log('Initializing module Auth');
        
        this._store = App.Store.AddChild('app.auth', {});
        
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

    Store() {
        return this._store;
    }

}

App.Modules.Auth.Session = class extends Colibri.IO.RpcRequest  {

    constructor() {
        super('Auth');
    }

    Start() {
        return new Promise((resolve, reject) => {
            this.Call('Session', 'Start').then((session) => {
                Auth.Store.Set('session', session);
                resolve(session);
            }).catch(response => reject(response));
        });
    }

    Login(login, password) {
        return new Promise((resolve, reject) => {
            this.Call('Session', 'Login', {login: login, password: password}).then((session) => {
                Auth.Store.Set('session', session);
                resolve(session);
            }).catch(response => reject(response));
        });
    }

    Logout() {
        return new Promise((resolve, reject) => {
            this.Call('Session', 'Logout').then((session) => {
                Auth.Store.Set('session', session);
                resolve(session);
            }).catch(response => reject(response));
        });
    }

}

App.Modules.Auth.Members = class extends Colibri.IO.RpcRequest  {

    constructor() {
        super('Auth');
    }

    Register(email, phone, password, confirmation, firstName, lastName, patronymic = '', gender = 'male', birthdate = null) {
        return new Promise((resolve, reject) => {
            this.Call('Member', 'Register', {
                email: email,
                phone: phone,
                password: password,
                confirmation: confirmation,
                firstName: firstName,
                lastName: lastName,
                patronymic: patronymic,
                gender: gender,
                birthdate: birthdate,
                role: 'user'
            }).then((session) => {
                Auth.Store.Set('session', session);
                resolve(session);
            }).catch(response => reject(response));
        });
    }

    BeginConfirmationProcess(property = 'email') {
        return new Promise((resolve, reject) => {
            this.Call('Member', 'BeginConfirmationProcess', {property: property}).then((session) => {
                Auth.Store.Set('session', session);
                resolve(session);
            }).catch(response => reject(response));
        });
    }

    ConfirmProperty(code, property = 'email') {
        return new Promise((resolve, reject) => {
            this.Call('Member', 'ConfirmProperty', {property: property, code: code}).then((session) => {
                Auth.Store.Set('session', session);
                resolve(session);
            }).catch(response => reject(response));
        });
    }

}

App.Modules.Auth.Application = class extends Colibri.IO.RpcRequest  {

    constructor() {
        super('Auth');
    }

    Settings() {
        return new Promise((resolve, reject) => {
            this.Call('App', 'Settings').then((settings) => {
                Auth.Store.Set('settings', settings);
                resolve(settings);
            }).catch(response => reject(response));
        });
    }

}

App.Modules.Auth.Icons = {};

const Auth = new App.Modules.Auth();
const AuthExternal = {
    Store: Auth.Store,
    Session: App.Modules.Auth.Session,
    Member: App.Modules.Auth.Member,
    App: App.Modules.Auth.Application
}

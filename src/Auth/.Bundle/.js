


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

}

App.Modules.Auth.Icons = {};

const Auth = new App.Modules.Auth();


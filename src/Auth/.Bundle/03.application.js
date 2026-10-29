/**
 * Auth module application class
 * @class
 * @extends {Colibri.IO.RpcRequest}
 * @memberof App.Modules.Auth
 */
App.Modules.Auth.Application = class extends Colibri.IO.RpcRequest  {

    /**
     * @constructor
     */
    constructor() { 
        super('Auth', Auth.requestType, Auth.remoteDomain);
    }

    /**
     * Retrieves application settings
     * @public
     * @returns {Promise} - Promise that resolves with the application settings
     * @async
     */
    Settings() {
        return new Promise((resolve, reject) => {
            this.Call('App', 'Settings', {}, {'X-AppToken': Auth.appToken, 'DeviceId': App.Device.id, 'Colibri-Language': Auth.useLanguage}).then((settings) => {
                Auth.Store.Set('auth.settings', settings.result);
                resolve(settings.result);
            }).catch(response => reject(response));
        });
    }

}
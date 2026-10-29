/**
 * Auth module session class
 * @class
 * @extends {Colibri.IO.RpcRequest}
 * @memberof App.Modules.Auth
 */
App.Modules.Auth.Session = class extends Colibri.IO.RpcRequest  {

    /**
     * @constructor
     */
    constructor() {
        super('Auth', Auth.requestType, Auth.remoteDomain);
    }

    /**
     * Starts a new session
     * @public
     * @returns {Promise} - Promise that resolves with the session data
     * @async
     */
    Start() {
        return new Promise((resolve, reject) => {
            this.Call('Session', 'Start', {}, {'X-AppToken': Auth.appToken, 'DeviceId': App.Device.id, 'Colibri-Language': Auth.useLanguage}).then((response) => {
                Auth.Store.Set('auth.session', response.result.session);
                resolve(response.result.session);
            }).catch(response => reject(response));
        });
    }

    /**
     * Logins user by credentials
     * @public
     * @returns {Promise} - Promise that resolves with the session data
     * @async
     */
    LoginByCreds(creds) {
        return new Promise((resolve, reject) => {
            this.Call('Session', 'LoginByCreds', {credentials: creds}, {'X-AppToken': Auth.appToken, 'DeviceId': App.Device.id, 'Colibri-Language': Auth.useLanguage}).then((response) => {
                Auth.Store.Set('auth.session', response.result.session);
                resolve(response.result.session);
            }).catch(response => {
                reject(response);
            });
        });
    }

    /**
     * Logins user by login, password and optional code for 2FA
     * @public
     * @returns {Promise} - Promise that resolves with the session data
     * @async
     */
    Login(login, password, code) {
        return new Promise((resolve, reject) => {
            this.Call('Session', 'Login', {login: login, password: password, code: code}, {'X-AppToken': Auth.appToken, 'DeviceId': App.Device.id, 'Colibri-Language': Auth.useLanguage}).then((response) => {
                Auth.Store.Set('auth.session', response.result.session);
                resolve({session: response.result.session, code: response.result.code});
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

    /**
     * Logouts current user
     * @public
     * @returns {Promise} - Promise that resolves with the session data
     * @async
     */
    Logout() {
        return new Promise((resolve, reject) => {
            this.Call('Session', 'Logout', {}, {'X-AppToken': Auth.appToken, 'DeviceId': App.Device.id, 'Colibri-Language': Auth.useLanguage}).then((response) => {
                resolve(response.result.session);
                Auth.Store.Set('auth.session', response.result.session);
            }).catch(response => reject(response));
        });
    }

    /**
     * Logouts current user from all devices
     * @public
     * @returns {Promise} - Promise that resolves with the session data
     * @async
     */
    LogoutFromAll() {
        return new Promise((resolve, reject) => {
            this.Call('Session', 'LogoutFromAll', {}, {'X-AppToken': Auth.appToken, 'DeviceId': App.Device.id, 'Colibri-Language': Auth.useLanguage}).then((response) => {
                Auth.Store.Set('auth.session', response.result.session);
                resolve(response.result.session);
            }).catch(response => reject(response));
        });
    }

}
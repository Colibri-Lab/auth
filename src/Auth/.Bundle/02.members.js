/**
 * Members class for Auth module
 * @class
 * @extends {Colibri.IO.RpcRequest}
 * @memberof App.Modules.Auth
 */
App.Modules.Auth.Members = class extends Colibri.IO.RpcRequest  {

    /**
     * @constructor
     */
    constructor() {
        super('Auth', Auth.requestType, Auth.remoteDomain);
    }

    /**
     * Registers a new member
     * @public
     * @param {string} login - The login of the new member
     * @param {string} email - The email of the new member
     * @param {boolean} email_confirmed - Whether the email is confirmed
     * @param {string} phone - The phone number of the new member
     * @param {boolean} phone_confirmed - Whether the phone number is confirmed
     * @param {string} password - The password for the new member
     * @param {string} confirmation - The confirmation code for registration
     * @param {string} [first_name] - The first name of the new member (optional)
     * @param {string} [last_name] - The last name of the new member (optional)
     * @param {string} [patronymic] - The patronymic of the new member (optional)
     * @param {string} [gender] - The gender of the new member (optional, default is 'male')
     * @param {string|null} [birthdate] - The birthdate of the new member (optional, default is null)
     * @param {string|null} [invitation] - The invitation code for the new member (optional, default is null)
     * @returns {Promise} - Promise that resolves with the session data of the newly registered member
     * @async
     */
    Register(login, email, email_confirmed, phone, phone_confirmed, password, confirmation, first_name = '', last_name = '', patronymic = '', gender = 'male', birthdate = null, invitation = null) {
        return new Promise((resolve, reject) => {
            this.Call('Member', 'Register', {
                login: login,
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
            }, {'X-AppToken': Auth.appToken, 'DeviceId': App.Device.id, 'Colibri-Language': Auth.useLanguage}).then((response) => {
                Auth.Store.Set('auth.session', response.result.session);
                resolve(response.result.session);
            }).catch(response => reject(response));
        });
    }

    /**
     * Gets the invite
     * @param {boolean} returnPromise - indicates if the method should return a promise
     * @returns {Promise} - returns a promise if returnPromise is true
     * @public 
     * @async
     */
    GetInvite(code) {
        return new Promise((resolve, reject) => {
            this.Call('Invites', 'Get', {code: code}, {'X-AppToken': Auth.appToken, 'DeviceId': App.Device.id, 'Colibri-Language': Auth.useLanguage}).then((response) => {
                resolve(response.result);
            }).catch(response => reject(response));
        });
    }

    /**
     * Invites a new member
     * @param {string} email - The email of the member to invite
     * @param {string} fio - The full name of the member to invite
     * @param {object} params - Additional parameters for the invitation
     * @returns {Promise} - Promise that resolves with the invitation result
     * @async
     * @public
     */
    Invite(email, fio, params) {
        return new Promise((resolve, reject) => {
            this.Call('Invites', 'Create', {
                email: email,
                fio: fio,
                params: params
            }, {'X-AppToken': Auth.appToken, 'DeviceId': App.Device.id, 'Colibri-Language': Auth.useLanguage}).then((response) => {
                resolve(response.result);
            }).catch(response => reject(response));
        });
    }

    /**
     * Begin confirmation process for a member's property (email or phone)
     * @param {string} property - The property to confirm ('email' or 'phone')
     * @param {string} value - The value of the property to confirm
     * @returns {Promise} - Promise that resolves with the session data
     * @async
     * @public
     */
    BeginConfirmationProcess(property = 'email', value = '') {
        return new Promise((resolve, reject) => {
            this.Call('Member', 'BeginConfirmationProcess', {property: property, value: value}, {'X-AppToken': Auth.appToken, 'DeviceId': App.Device.id, 'Colibri-Language': Auth.useLanguage}).then((response) => {
                // Auth.Store.Set('auth.session', response.result.session);
                resolve(response.result.session);
            }).catch(response => reject(response));
        });
    }

    /**
     * Confirms a member's property (email or phone) using a confirmation code
     * @param {string} code - The confirmation code
     * @param {string} property - The property to confirm ('email' or 'phone')
     * @param {string} value - The value of the property to confirm
     * @returns {Promise} - Promise that resolves with the session data
     * @async
     * @public
     */
    ConfirmProperty(code, property = 'email', value = '') {
        return new Promise((resolve, reject) => {
            this.Call('Member', 'ConfirmProperty', {property: property, code: code, value: value}, {'X-AppToken': Auth.appToken, 'DeviceId': App.Device.id, 'Colibri-Language': Auth.useLanguage}).then((response) => {
                Auth.Store.Set('auth.session', response.result.session);
                resolve(response.result.session);
            }).catch(response => reject(response));
        });
    }

    /**
     * Begins the password reset process for a member
     * @param {string} email - The email of the member
     * @param {string} phone - The phone number of the member
     * @returns {Promise} - Promise that resolves with the session data
     * @async
     * @public
     */
    BeginPasswordResetProcess(email, phone) {
        return new Promise((resolve, reject) => {
            this.Call('Member', 'BeginPasswordResetProcess', {email: email, phone: phone}, {'X-AppToken': Auth.appToken, 'Colibri-Language': Auth.useLanguage}).then((response) => {
                // Auth.Store.Set('auth.session', response.result.session);
                resolve(response.result.session);
            }).catch(response => reject(response));
        });
    }

    /**
     * Resets the password for a member
     * @param {string} email - The email of the member
     * @param {string} phone - The phone number of the member
     * @param {string} code - The confirmation code for password reset
     * @param {string} password - The new password for the member
     * @param {string} confirmation - The confirmation of the new password
     * @returns {Promise} - Promise that resolves with the session data
     * @async
     * @public
     */
    ResetPassword(email, phone, code, password, confirmation) {
        return new Promise((resolve, reject) => {
            this.Call('Member', 'ResetPassword', {email: email, phone: phone, code: code, password: password, confirmation: confirmation}, {'X-AppToken': Auth.appToken, 'DeviceId': App.Device.id}).then((response) => {
                // Auth.Store.Set('auth.session', response.result.session);
                resolve(response.result.session);
            }).catch(response => reject(response));
        });
    }

    /**
     * Begins the identity update process for a member's property (email or phone)
     * @param {string} value - The new value of the property to update
     * @param {string} property - The property to update ('email' or 'phone')
     * @returns {Promise} - Promise that resolves with the session data
     * @async
     * @public
     */
    BeginIdentityUpdateProcess(value, property = 'email') {
        return new Promise((resolve, reject) => {
            this.Call('Member', 'BeginIdentityUpdateProcess', {property: property, value: value}, {'X-AppToken': Auth.appToken, 'DeviceId': App.Device.id, 'Colibri-Language': Auth.useLanguage}).then((response) => {
                // Auth.Store.Set('auth.session', response.result.session);
                resolve(response.result.session);
            }).catch(response => reject(response));
        });
    }

    /** 
     * Changes the identity of a member's property (email or phone) using a confirmation code
     * @param {string} code - The confirmation code for identity change
     * @param {string} value - The new value of the property to change
     * @param {string} property - The property to change ('email' or 'phone')
     * @returns {Promise} - Promise that resolves with the session data
     * @async
     * @public
     */
    ChangeIdentity(code, value, property = 'email') {
        return new Promise((resolve, reject) => {
            this.Call('Member', 'ChangeIdentity', {property: property, code: code, value: value}, {'X-AppToken': Auth.appToken, 'DeviceId': App.Device.id}).then((response) => {
                Auth.Store.Set('auth.session', response.result.session);
                resolve(response.result.session);
            }).catch(response => reject(response));
        });
    }

    /**
     * Saves the profile information of a member
     * @param {string} last_name - The last name of the member
     * @param {string} first_name - The first name of the member
     * @param {string|null} patronymic - The patronymic of the member (optional)
     * @param {string|null} birthdate - The birthdate of the member (optional)
     * @param {string|null} gender - The gender of the member (optional)
     * @returns {Promise} - Promise that resolves with the session data
     * @async
     * @public
     */
    SaveProfile(last_name, first_name, patronymic = null, birthdate = null, gender = null) {
        return new Promise((resolve, reject) => {
            this.Call('Member', 'UpdateProfile', {last_name: last_name, first_name: first_name, patronymic: patronymic, birthdate: birthdate, gender: gender}, {'X-AppToken': Auth.appToken, 'DeviceId': App.Device.id, 'Colibri-Language': Auth.useLanguage}).then((response) => {
                Auth.Store.Set('auth.session', response.result.session);
                resolve(response.result.session);
            }).catch(response => reject(response));
        });
    }

    /**
     * Changes the password of a member
     * @param {string} original - The original password of the member
     * @param {string} password - The new password for the member
     * @param {string} confirmation - The confirmation of the new password
     * @returns {Promise} - Promise that resolves with the session data
     * @async
     * @public
     */
    ChangePassword(original, password, confirmation) {
        return new Promise((resolve, reject) => {
            this.Call('Member', 'ChangePassword', {original: original, password: password, confirmation: confirmation}, {'X-AppToken': Auth.appToken, 'DeviceId': App.Device.id}).then((response) => {
                // Auth.Store.Set('auth.session', response.result.session);
                resolve(response.result.session);
            }).catch(response => reject(response));
        });
    }

    /**
     * Changes the password of a member from the backend
     * @param {string} member - The member identifier
     * @param {string} password - The new password for the member
     * @returns {Promise} - Promise that resolves with the session data
     * @async
     * @public
     */
    ChangePasswordBackend(member, password) {
        return new Promise((resolve, reject) => {
            this.Call('Backend', 'ChangePassword', { member, password }, {}).then((response) => {
                // Auth.Store.Set('auth.session', response.result.session);
                resolve(response.result.session);
            }).catch(response => reject(response));
        });
    }

    /**
     * Blocks the account of the currently authenticated member
     * @returns {Promise} - Promise that resolves with the session data
     * @async
     * @public
     */
    BlockAccount() {
        return new Promise((resolve, reject) => {
            this.Call('Member', 'BlockAccount', {}, {'X-AppToken': Auth.appToken, 'DeviceId': App.Device.id, 'Colibri-Language': Auth.useLanguage}).then((response) => {
                Auth.Store.Set('auth.session', response.result.session);
                resolve(response.result.session);
            }).catch(response => reject(response));
        });
    }

    /**
     * Toggles the two-factor authentication for the currently authenticated member
     * @returns {Promise} - Promise that resolves with the session data
     * @async
     * @public
     */
    ToggleTwoFactorAuth() {
        return new Promise((resolve, reject) => {
            this.Call('Member', 'ToggleTwoFactorAuth', {}, {'X-AppToken': Auth.appToken, 'DeviceId': App.Device.id, 'Colibri-Language': Auth.useLanguage}).then((response) => {
                Auth.Store.Set('auth.session', response.result.session);
                resolve(response.result.session);
            }).catch(response => reject(response));
        });
    }

    /**
     * Toggles the two-factor authentication using an app for the currently authenticated member
     * @returns {Promise} - Promise that resolves with the session data
     * @async
     * @public
     */
    ToggleTwoFactorAppAuth() {
        return new Promise((resolve, reject) => {
            this.Call('Member', 'ToggleTwoFactorAppAuth', {}, {'X-AppToken': Auth.appToken, 'DeviceId': App.Device.id, 'Colibri-Language': Auth.useLanguage}).then((response) => {
                Auth.Store.Set('auth.session', response.result.session);
                if(response.result.code) {
                    const wnd = new App.Modules.Auth.Windows.TwoFactorAppInformation('twofactorapp-information', document.body, 800);
                    wnd.Show(response.result.session.member.email, Auth.appToken, response.result.code, response.result.qrcode).then((code) => {
                        this.Call('Member', 'CheckTwoFactorAppAuth', {code: code}, {'X-AppToken': Auth.appToken, 'DeviceId': App.Device.id, 'Colibri-Language': Auth.useLanguage}).then((response) => {
                            if(response.result.checked) {
                                wnd.Dispose();
                                resolve(response.result.session);
                            } else {
                                wnd.Dispose();
                                reject();
                            }
                        }).catch(response => reject(response));
                    }).catch(() => {
                        wnd.Dispose();
                        this.ToggleTwoFactorAppAuth();
                    });
                }
            }).catch(response => reject(response));
        });
    }
    
    /**
     * Requests an auto-login link for the currently authenticated member
     * @param {string} memberToken - The member token
     * @param {string} returnTo - The URL to return to after login
     * @returns {Promise} - Promise that resolves with the session data
     * @async
     * @public
     */
    RequestAutoLogin(memberToken, returnTo) {
        return new Promise((resolve, reject) => {
            this.Call('Member', 'RequestAutologin', {token: memberToken, return: returnTo}, {'X-AppToken': Auth.appToken, 'DeviceId': App.Device.id, 'Colibri-Language': Auth.useLanguage}).then((response) => {
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

    /**
     * Requests an auto-login link from the backend for a member
     * @param {string} appToken - The application token
     * @param {string} memberToken - The member token
     * @param {string} returnTo - The URL to return to after login
     * @returns {Promise} - Promise that resolves with the session data
     * @async
     * @public
     */
    RequestAutoLoginBackend(appToken, memberToken, returnTo) {
        return new Promise((resolve, reject) => {
            this.Call('Backend', 'RequestAutologin', {app: appToken, token: memberToken, return: returnTo}).then((response) => {
                const w = new App.Modules.Auth.Components.AutologinRequest('autologin-window', document.body);
                w.Show(response.result.link);
                w.AddHandler('WindowClosed', (event, args) => {
                    w.Dispose();
                });
                resolve({});
            }).catch(response => reject(response));
        });
    }

    /**
     * @ignore
     * @private
     */
    _exportKeyAsPEM(key, type) {
        return new Promise((resolve, reject) => {
            window.crypto.subtle.exportKey(type === 'PUBLIC' ? 'spki' : 'pkcs8', type === 'PUBLIC' ? key.publicKey : key.privateKey).then(exportedKey => {
                let exportedAsString = exportedKey.toString();
                exportedAsString = window.btoa(exportedAsString);
                const pemString = `-----BEGIN ${type} KEY-----\n${exportedAsString}\n-----END ${type} KEY-----\n`;
                resolve(pemString);
            }).catch(error => {
                reject('Key export error: ' . error);
            });
        })
    }

    /**
     * @ignore
     * @private
     */
    _importKeyAsPEM(key, type) {
        return new Promise((resolve, reject) => {
            const pemString = key.replace(/-----BEGIN (.+?)-----/g, '').replace(/-----END (.+?)-----/g, '').replace(/\s/g, '');
            const binaryDerString = window.atob(pemString);
            const binaryDer = new Uint8Array(binaryDerString.length);
            for (let i = 0; i < binaryDerString.length; i++) {
                binaryDer[i] = binaryDerString.charCodeAt(i);
            }
            window.crypto.subtle.importKey(type === 'PUBLIC' ? 'spki' : 'pkcs8', binaryDer.buffer, { 
                name: "RSA-OAEP",
                hash: "SHA-256" 
            }, true, type === 'PUBLIC' ? ['encrypt'] : ['decrypt']).then(key => {
                resolve(key);
            }).catch(error => {
                reject('Key import error: ' + error);
            });
        });
    }

    /**
     * Generates a new RSA key pair for encryption and decryption
     * @returns {Promise} - Promise that resolves with the generated key pair in PEM format
     * @async
     * @public
     */
    GenerateKeyPair() {
        return new Promise((resolve, reject) => {
            window.crypto.subtle.generateKey(
                {
                    name: "RSA-OAEP",
                    modulusLength: 4096,
                    publicExponent: new Uint8Array([1, 0, 1]),
                    hash: "SHA-256",
                },
                true,
                ["encrypt", "decrypt"],
            ).then((keyPair) => {
                Promise.all([
                    this._exportKeyAsPEM(keyPair, 'PUBLIC'),
                    this._exportKeyAsPEM(keyPair, 'PRIVATE')
                ]).then(responses => {
                    resolve({ public: responses[0], private: responses[1] });
                });
            });
        });
    }

    /**
     * Saves the local public key for the currently authenticated member
     * @param {string} publicKey - The public key to save
     * @returns {Promise} - Promise that resolves with the result of the save operation
     * @async
     * @public
     */
    SaveLocalPublicKey(publicKey) {
        return new Promise((resolve, reject) => {
            this.Call('Member', 'SaveLocalPublicKey', {public: publicKey}, {'X-AppToken': Auth.appToken, 'DeviceId': App.Device.id, 'Colibri-Language': Auth.useLanguage}).then((response) => {
                resolve(response.result);
            }).catch(response => reject(response));
        });
    }

    /**
     * Encrypts a message locally using the provided public key
     * @param {string} message - The message to encrypt
     * @param {string} publicKey - The public key to use for encryption
     * @returns {Promise} - Promise that resolves with the encrypted message
     * @async
     * @public
     */
    EncryptLocal(message, publicKey) {
        return new Promise((resolve, reject) => {
            this._importKeyAsPEM(publicKey, 'PUBLIC').then(publicKey => {
                let encoded = new TextEncoder().encode(message);
                window.crypto.subtle.encrypt(
                    {
                        name: "RSA-OAEP",
                    },
                    publicKey,
                    encoded,
                ).then(ciphertext => {
                    resolve(ciphertext.toString());
                }).catch((er) => {
                    reject(er)
                });
            }).catch((er) => {
                reject(er)
            });
        });
    }

    /**
     * Decrypts a message locally using the provided private key
     * @param {string} ciphertext - The encrypted message to decrypt
     * @param {string} privateKey - The private key to use for decryption
     * @returns {Promise} - Promise that resolves with the decrypted message
     * @async
     * @public
     */
    DecodeLocal(ciphertext, privateKey) {
        return new Promise((resolve, reject) => {
            this._importKeyAsPEM(privateKey, 'PRIVATE').then(privateKey => {
                window.crypto.subtle.decrypt(
                    {
                        name: "RSA-OAEP",
                    },
                    privateKey,
                    ciphertext.toArrayBuffer()
                ).then(decrypted => {
                    resolve(new TextDecoder().decode(decrypted));
                }).catch((er) => {
                    reject(er);
                }) 
            }).catch((er) => {
                reject(er)
            });
        });
    }

    /**
     * Encrypts a message for a specific member using the backend
     * @param {string} message - The message to encrypt
     * @param {string} member - The member identifier for whom the message is encrypted
     * @returns {Promise} - Promise that resolves with the encrypted message
     * @async
     * @public
     */
    Encrypt(message, member) {
        return new Promise((resolve, reject) => {
            this.Call('Member', 'EncryptMessage', {message: message, for: member}, {'X-AppToken': Auth.appToken, 'DeviceId': App.Device.id, 'Colibri-Language': Auth.useLanguage}).then((response) => {
                resolve(response.result.encrypted);
            }).catch(response => reject(response));
        });
    }

    /**
     * Decrypts a message for the current member using the backend
     * @param {string} message - The message to decrypt
     * @returns {Promise} - Promise that resolves with the decrypted message
     * @async
     * @public
     */
    Decrypt(message) {
        return new Promise((resolve, reject) => {
            this.Call('Member', 'DecryptMessage', {message: message}, {'X-AppToken': Auth.appToken, 'DeviceId': App.Device.id, 'Colibri-Language': Auth.useLanguage}).then((response) => {
                resolve(response.result.decrypted);
            }).catch(response => reject(response));
        });
    }

    /**
     * Searches for members based on a search term
     * @param {string} term - The search term to use for finding members
     * @returns {Promise} - Promise that resolves with the search results
     * @async
     * @public
     */
    Search(term) {
        return new Promise((resolve, reject) => {
            this.Call('Member', 'Search', {term: term}, {'X-AppToken': Auth.appToken, 'DeviceId': App.Device.id, 'Colibri-Language': Auth.useLanguage}).then((response) => {
                resolve(response.result);
            }).catch(response => reject(response));
        });
    }

    /**
     * Creates device credentials for the currently authenticated member
     * @returns {Promise} - Promise that resolves with the created device credentials
     * @async
     * @public
     */
    CreateDeviceCredentials() {
        return new Promise((resolve, reject) => {
            const session = Auth.Store.Query('auth.session');
            App.Device.Auth.IsAvailable().then(() => {
                App.Device.Auth.Create(session.member.token, session.member.last_name + ' ' + session.member.first_name, session.member.email).then((credential) => {
                    this.Call('Member', 'CreateDevice', {credentials: credential}, {'X-AppToken': Auth.appToken, 'DeviceId': App.Device.id, 'Colibri-Language': Auth.useLanguage}).then((response) => {
                        resolve(response.result);
                    }).catch(response => reject(response));
                });
            })
        });
    }

}

/**
 * Two factor app information
 * @class
 * @extends Colibri.UI.Window
 * @memberof App.Modules.Auth.Windows
 */
App.Modules.Auth.Windows.TwoFactorAppInformation = class extends Colibri.UI.Window {
    
    /**
     * @constructor
     * @param {string} name name of component
     * @param {Element|Colibri.UI.component} container container of component
     */
    constructor(name, container, width) {
        /* создаем компонент и передаем шаблон */
        super(name, container, Colibri.UI.Templates['App.Modules.Auth.Windows.TwoFactorAppInformation'], '', width);
        this.AddClass('app-modules-auth-windows-twofactorappinformation');

        this._checkFlexCheck = this.Children('check-flex/buttons-flex/check');
        this._checkFlexButtonsFlexDelete = this.Children('check-flex/buttons-flex/delete');
        this._checkFlexInputCode = this.Children('check-flex/input-code');
        this._tfaFlexCode = this.Children('tfa-flex/code');
        this._tfaFlexTfa = this.Children('tfa-flex/tfa');

        this.closable = false;
        this.closableOnShadow = false;

    }

    Show(email, issuer, code, qrcode) {
        return new Promise((resolve, reject) => {
            super.Show();

            this._tfaFlexTfa.source = qrcode;
            this._tfaFlexCode.value = code;

            this._checkFlexCheck.ClearHandlers();
            this._checkFlexCheck.AddHandler('Clicked', (event, args) => {
                debugger;
                if(this._checkFlexInputCode.value) {
                    resolve(this._checkFlexInputCode.value);
                } 
            });
            
            this._checkFlexButtonsFlexDelete.ClearHandlers();
            this._checkFlexButtonsFlexDelete.AddHandler('Clicked', (event, args) => {
                debugger;
                reject();
            });
        });

    }

}
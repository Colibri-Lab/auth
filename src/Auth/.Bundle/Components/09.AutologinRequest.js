/**
 * Shows autologin link
 * @class
 * @extends Colibri.UI.Window
 * @memberof App.Modules.Auth.Components
 */
App.Modules.Auth.Components.AutologinRequest = class extends Colibri.UI.Window {
    
    /**
     * @constructor
     * @param {string} name name of component
     * @param {Element|Colibri.UI.component} container container of component
     */
    constructor(name, container) {
        /* создаем компонент и передаем шаблон */
        super(name, container, Colibri.UI.Templates['App.Modules.Auth.Components.AutologinRequest']);
        this.AddClass('app-modules-auth-components-autologinrequest');

        this.title = '#{auth-autologin-title}';
        this._link = this.Children('link');
        this._copy = this.Children('copy');
        
        this._copy.AddHandler('Clicked', (event, args) => this.__copyClicked(event, args)); 
    }

    __copyClicked(event, args) {
        this._link.value.copyToClipboard();
        App.Notices.Add(new Colibri.UI.Notice('#{auth-autologin-copied}', Colibri.UI.Notice.Success));
        this.Close();
    }

    Show(link) {
        this._link.value = link;
        super.Show();
    }

}
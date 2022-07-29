App.Modules.Auth.Components.LoginForm = class extends Colibri.Ui.Component  {
    
    constructor(name, container) {
        /* создаем компонент и передаем шаблон */
        super(name, container, Colibri.UI.Templates['App.Modules.Auth.Components.LoginForm']);
 
        this.AddClass('app-auth-login-form-component'); 

    } 
}
App.Modules.Auth.Components.ProfileForm = class extends Colibri.UI.Component  {
    
    constructor(name, container) {
        /* создаем компонент и передаем шаблон */
        super(name, container, Colibri.UI.Templates['App.Modules.Auth.Components.ProfileForm']);
 
        this.AddClass('app-auth-form-component'); 
        this.AddClass('app-auth-profile-form-component'); 

        this._form = this.Children('form-container/form'); 
        this._validator = new App.Modules.Auth.Forms.Validator(this._form);

        this._saveButton = this.Children('button-container/save');
        this._form.AddHandler('Changed', (event, args) => {
            this._saveButton.enabled = this._validator.Status();
        });
        
        this._saveButton.AddHandler('Clicked', (event, args) => this.__profileFormSaveButtonClicked(event, args));

    } 

    set shown(value) {
        super.shown = value;
        this._form.Focus();
    }
    
    set value(value) {
        this._form.value = value;
    }

    get value() {
        return this._form.value;
    }

    __profileFormSaveButtonClicked(event, args) {
        
        if(!this._validator.ValidateAll()) {
            return;
        }

        Auth.Members.SaveProfile(this._form.value.last_name, this._form.value.first_name, this._form.value.patronymic, this._form.value.birthdate, this._form.value.gender).then((session) => {
            this.Hide();
        }).catch(response => {
            response.result = JSON.parse(response.result);
            if(response.result.validation && Object.keys(response.result.validation).length > 0) {
                Object.forEach(response.result.validation, (field, message, index) => {
                    if(['password', 'confirmation'].indexOf(field) !== -1) {
                        field = 'pass';
                    }
                    this._validator.Invalidate(field, message);
                    if(index === 0) {
                        this._form.FindField(field).Focus();
                    }
                });
            }
            else {
                this._validator.Invalidate('last_name', response.result.message);
                this._form.Children('last_name').Focus();
            }
        });

    }

}
App.Modules.Auth.Components.ChangePassForm = class extends Colibri.UI.Component {

    constructor(name, container) {
        /* создаем компонент и передаем шаблон */
        super(name, container, Colibri.UI.Templates['App.Modules.Auth.Components.ChangePassForm']);

        this.AddClass('app-auth-form-component');
        this.AddClass('app-auth-changepass-form-component');

        this._form = this.Children('form-container/form');
        this._validator = new Colibri.UI.SimpleFormValidator(this._form);

        this._saveButton = this.Children('button-container/save');
        
        this._form.AddHandler('Changed', this.__formChanged, false, this);
        this._saveButton.AddHandler('Clicked', this.__profileFormSaveButtonClicked, false, this);

    }

    __formChanged(event, args) {
        this._saveButton.enabled = this._validator.Status();
    }

    /** @protected */
    _registerEvents() {
        super._registerEvents();
        this.RegisterEvent('Completed', true, 'When password is changed');
    }


    /**
     * Shows the component
     * @type {boolean}
     */
    set shown(value) {
        super.shown = value;
        this._form.Focus();
    }

    /**
     * Value object
     * @type {object}
     */
    set value(value) {
        this._form.value = value;
    }

    get value() {
        return this._form.value;
    }

    /**
     * @private
     * @param {Colibri.Events.Event} event event object
     * @param {*} args event arguments
     */
    __profileFormSaveButtonClicked(event, args) {

        if (!this._validator.ValidateAll()) {
            return;
        }

        Auth.Members.ChangePassword(this._form.value.original, this._form.value.password, this._form.value.confirmation).then((session) => {
            this.Hide();
        }).then(session => {
            this.Dispatch('Completed', session);
        }).catch(response => {
            response.result = (typeof response.result === 'string' ? JSON.parse(response.result) : response.result);
            if (response.result.validation && Object.keys(response.result.validation).length > 0) {
                Object.forEach(response.result.validation, (field, message, index) => {
                    if (['password', 'confirmation'].indexOf(field) !== -1) {
                        field = 'pass';
                    }
                    this._validator.Invalidate(field, message);
                    if (index === 0) {
                        this._form.FindField(field).Focus();
                    }
                });
            }
            else {
                this._validator.Invalidate('form', response.result.message);
                this._form.Focus();
            }
        });

    }

}
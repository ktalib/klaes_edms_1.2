function instrumentTypesManager() {
    return {
        isOpen: false,
        viewMode: 'list', // 'list' or 'form'
        formMode: 'add', // 'add' or 'edit'
        types: [],

        // Form Data
        formData: {
            id: null,
            name: '',
            description: '',
            last_volume: '',
            last_page: '',
            last_serial: ''
        },

        init() {
            // Initial fetch or setup if needed
        },

        openModal() {
            this.isOpen = true;
            this.viewMode = 'list';
            this.fetchTypes();
        },

        closeModal() {
            this.isOpen = false;
            this.resetForm();
            this.viewMode = 'list';
        },

        resetForm() {
            this.formData = {
                id: null,
                name: '',
                description: '',
                last_volume: '',
                last_page: '',
                last_serial: ''
            };
            this.formMode = 'add';
        },

        async fetchTypes() {
            try {
                const response = await fetch(`${window.baseUrl}/instrument-types`);
                if (!response.ok) throw new Error('Failed to fetch');
                this.types = await response.json();
            } catch (error) {
                console.error('Error fetching types:', error);
                // alert('Failed to load instrument types.'); // Optional: suppress alert on background fetch
            }
        },

        openAddForm() {
            this.resetForm();
            this.formMode = 'add';
            this.viewMode = 'form';
        },

        openEditForm(type) {
            this.formData = {
                id: type.id,
                name: type.name,
                description: type.description || '',
                last_volume: type.last_volume,
                last_page: type.last_page,
                last_serial: type.last_serial
            };
            this.formMode = 'edit';
            this.viewMode = 'form';
        },

        cancelForm() {
            this.resetForm();
            this.viewMode = 'list';
        },

        async submitForm() {
            if (!this.formData.name.trim()) {
                alert('Name is required');
                return;
            }

            const isEdit = this.formMode === 'edit';
            const url = isEdit
                ? `${window.baseUrl}/instrument-types/${this.formData.id}`
                : `${window.baseUrl}/instrument-types`;

            const method = isEdit ? 'PUT' : 'POST';

            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

                const response = await fetch(url, {
                    method: method,
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify(this.formData)
                });

                if (response.ok) {
                    await this.fetchTypes();
                    this.viewMode = 'list';
                    this.resetForm();
                } else {
                    const data = await response.json();
                    alert(data.message || 'Operation failed.');
                }
            } catch (error) {
                console.error('Error submitting form:', error);
                alert('An error occurred.');
            }
        },

        async deleteType(id) {
            if (!confirm('Are you sure you want to delete this instrument type?')) return;

            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
                const response = await fetch(`${window.baseUrl}/instrument-types/${id}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken
                    }
                });

                if (response.ok) {
                    this.fetchTypes();
                } else {
                    alert('Failed to delete type.');
                }
            } catch (error) {
                console.error('Error deleting type:', error);
            }
        }
    }
}

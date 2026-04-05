// bildgalleri.js - Fullständig och testad kod

// Vänta tills DOM är klar
document.addEventListener('DOMContentLoaded', function() {
    
    // ========================================
    // HÄMTA PROJEKT-ID
    // ========================================
    let projektId = null;
    
    // Försök från hidden input
    const projektIdInput = document.getElementById('projekt_id');
    if (projektIdInput && projektIdInput.value) {
        projektId = projektIdInput.value;
    }
    
    // Försök från URL
    if (!projektId) {
        const urlParams = new URLSearchParams(window.location.search);
        projektId = urlParams.get('id');
    }
    
    if (!projektId) {
        console.error('Kunde inte hitta projekt-ID');
        return;
    }
    
    console.log('Bildgalleri startat för projekt:', projektId);
    
    // ========================================
    // FUNKTION: LADDA BILDER
    // ========================================
    function laddaBilder() {
        const galleri = document.getElementById('bildgalleri');
        if (!galleri) {
            console.error('Element #bildgalleri saknas');
            return;
        }
        
        galleri.innerHTML = '<div class="text-center p-5 text-muted"><i class="fas fa-spinner fa-spin fa-2x"></i><p class="mt-2">Laddar bilder...</p></div>';
        
        fetch('../ajax/hamta_bilder.php?projekt_id=' + projektId)
            .then(function(response) {
                return response.json();
            })
            .then(function(response) {
                console.log('Bilder svar:', response);
                
                if (response.success && response.bilder && response.bilder.length > 0) {
                    var html = '';
                    for (var i = 0; i < response.bilder.length; i++) {
                        var bild = response.bilder[i];
                        html += '<div class="col-md-3 col-sm-4 col-6 mb-3">';
                        html += '<div class="card h-100">';
                        html += '<img src="' + bild.sokvag_full + '" class="card-img-top" style="height: 180px; object-fit: cover; cursor: pointer;" onclick="visaBild(\'' + bild.sokvag_full + '\', \'' + escapeHtml(bild.original_namn) + '\')">';
                        html += '<div class="card-body p-2">';
                        html += '<small class="text-muted d-block">' + bild.uppladdad_datum_formaterad + '</small>';
                        html += '<small class="text-muted d-block">' + bild.filstorlek_formaterad + '</small>';
                        html += '<small class="text-muted d-block">Av: ' + (bild.uppladdad_av_namn || 'Okänd') + '</small>';
                        html += '<button class="btn btn-sm btn-danger mt-2 w-100" onclick="taBortBild(' + bild.id + ')">';
                        html += '<i class="fas fa-trash"></i> Ta bort';
                        html += '</button>';
                        html += '</div>';
                        html += '</div>';
                        html += '</div>';
                    }
                    galleri.innerHTML = html;
                } else {
                    galleri.innerHTML = '<div class="col-12 text-center p-5 text-muted">' +
                        '<i class="fas fa-camera fa-3x mb-3"></i>' +
                        '<p>Inga bilder uppladdade ännu</p>' +
                        '<button class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#uploadModal">' +
                        '<i class="fas fa-upload"></i> Ladda upp första bilden' +
                        '</button>' +
                        '</div>';
                }
            })
            .catch(function(error) {
                console.error('Fel vid laddning:', error);
                galleri.innerHTML = '<div class="col-12 text-center p-5 text-danger">Kunde inte ladda bilder</div>';
            });
    }
    
    // ========================================
    // FUNKTION: TA BORT BILD
    // ========================================
    window.taBortBild = function(bildId) {
        if (!confirm('Är du säker på att du vill ta bort denna bild?')) {
            return;
        }
        
        fetch('../ajax/ta_bort_bild.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: 'bild_id=' + bildId
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(response) {
            if (response.success) {
                laddaBilder();
            } else {
                alert('Kunde inte ta bort bilden: ' + (response.message || 'Okänt fel'));
            }
        })
        .catch(function(error) {
            console.error('Fel:', error);
            alert('Kunde inte ta bort bilden');
        });
    };
    
    // ========================================
    // FUNKTION: VISA BILD I LIGHTBOX
    // ========================================
    window.visaBild = function(src, caption) {
        var lightboxImage = document.getElementById('lightboxImage');
        var lightboxCaption = document.getElementById('lightboxCaption');
        
        if (lightboxImage && lightboxCaption) {
            lightboxImage.src = src;
            lightboxCaption.innerHTML = caption;
            
            var modalElement = document.getElementById('lightboxModal');
            var modal = new bootstrap.Modal(modalElement);
            modal.show();
        }
    };
    
    // ========================================
    // ESCAPE HTML
    // ========================================
    function escapeHtml(text) {
        if (!text) return '';
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // ========================================
    // UPPHANDLING AV BILDER
    // ========================================
    function initUpload() {
        var uploadBtn = document.getElementById('uploadBtn');
        if (!uploadBtn) {
            console.log('Upload-knapp finns inte på denna sida');
            return;
        }
        
        // Ta bort gamla event listeners
        var nyUploadBtn = uploadBtn.cloneNode(true);
        uploadBtn.parentNode.replaceChild(nyUploadBtn, uploadBtn);
        
        nyUploadBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            var form = document.getElementById('uploadForm');
            if (!form) {
                alert('Tekniskt fel: Formulär saknas');
                return;
            }
            
            var bildInput = document.getElementById('bild');
            if (!bildInput || !bildInput.files || bildInput.files.length === 0) {
                var msgDiv = document.getElementById('uploadMessage');
                if (msgDiv) {
                    msgDiv.innerHTML = '<div class="alert alert-danger mt-2">Vänligen välj en bild först</div>';
                } else {
                    alert('Vänligen välj en bild först');
                }
                return;
            }
            
            var formData = new FormData(form);
            
            var btn = this;
            var progressDiv = document.getElementById('uploadProgress');
            var messageDiv = document.getElementById('uploadMessage');
            var progressBar = progressDiv ? progressDiv.querySelector('.progress-bar') : null;
            
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Laddar upp...';
            if (progressDiv) progressDiv.classList.remove('d-none');
            if (messageDiv) messageDiv.innerHTML = '';
            if (progressBar) {
                progressBar.style.width = '0%';
                progressBar.textContent = '0%';
            }
            
            fetch('../ajax/upload_bild.php', {
                method: 'POST',
                body: formData
            })
            .then(function(response) {
                return response.json();
            })
            .then(function(response) {
                if (response.success) {
                    if (messageDiv) {
                        messageDiv.innerHTML = '<div class="alert alert-success mt-2">' + response.message + '</div>';
                    }
                    setTimeout(function() {
                        var modal = bootstrap.Modal.getInstance(document.getElementById('uploadModal'));
                        if (modal) modal.hide();
                        laddaBilder();
                        form.reset();
                        if (progressDiv) progressDiv.classList.add('d-none');
                        if (progressBar) {
                            progressBar.style.width = '0%';
                            progressBar.textContent = '0%';
                        }
                        if (messageDiv) messageDiv.innerHTML = '';
                    }, 1000);
                } else {
                    if (messageDiv) {
                        messageDiv.innerHTML = '<div class="alert alert-danger mt-2">' + (response.message || 'Okänt fel') + '</div>';
                    }
                    if (progressDiv) progressDiv.classList.add('d-none');
                }
            })
            .catch(function(error) {
                console.error('Error:', error);
                if (messageDiv) {
                    messageDiv.innerHTML = '<div class="alert alert-danger mt-2">Ett fel uppstod vid uppladdning</div>';
                }
                if (progressDiv) progressDiv.classList.add('d-none');
            })
            .finally(function() {
                btn.disabled = false;
                btn.innerHTML = 'Ladda upp';
            });
        });
    }
    
    // ========================================
    // RESET MODAL
    // ========================================
    function initModalReset() {
        var uploadModal = document.getElementById('uploadModal');
        if (uploadModal) {
            uploadModal.addEventListener('hidden.bs.modal', function() {
                var form = document.getElementById('uploadForm');
                if (form) form.reset();
                var progressDiv = document.getElementById('uploadProgress');
                var messageDiv = document.getElementById('uploadMessage');
                if (progressDiv) progressDiv.classList.add('d-none');
                var progressBar = progressDiv ? progressDiv.querySelector('.progress-bar') : null;
                if (progressBar) {
                    progressBar.style.width = '0%';
                    progressBar.textContent = '0%';
                }
                if (messageDiv) messageDiv.innerHTML = '';
            });
        }
    }
    
    // ========================================
    // STARTA ALLT
    // ========================================
    laddaBilder();
    initUpload();
    initModalReset();
    
    console.log('Bildgalleri initialiserat');
});
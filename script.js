// Continue to handle bookings if the optional SweetAlert CDN is unavailable.
const clinicAlert = window.Swal || {
  fire(options) {
    if (options && options.title === 'Booking your appointment...') return;
    window.alert([options && options.title, options && options.text].filter(Boolean).join('\n'));
  },
  close() {},
  showLoading() {}
};

const serviceContent = {
  musculoskeletal: {
    title: "Musculoskeletal Physiotherapy",
    desc: `
      <p>Musculoskeletal physiotherapy, also known as orthopedic physiotherapy, is a specialized branch of physical therapy that focuses on the assessment, diagnosis, and treatment of musculoskeletal conditions affecting the muscles, bones, joints, ligaments, and tendons. We work to restore and optimize the function of the musculoskeletal system, alleviate pain, and improve overall mobility and quality of life for our patients.</p>
      <img src="images/injuredman.jpg" alt="Assessment and Diagnosis" class="section-image">

      <p><strong class="section-heading">Assessment and Diagnosis:</strong><br>
      We conduct thorough assessments to identify the underlying causes of musculoskeletal issues. This may involve evaluating posture, joint range of motion, muscle strength, flexibility, and functional movement patterns.</p>
      
      <p><strong class="section-heading">Treatment Planning:</strong><br>
      Based on the assessment findings, we develop personalized treatment plans. These plans often include a combination of therapeutic exercises, manual therapy, and other modalities to address specific issues and achieve therapeutic goals.</p>
      
      <p><strong class="section-heading">Manual Therapy:</strong><br>
      We use hands-on techniques such as joint mobilization, soft tissue massage, and stretching to improve joint mobility, reduce pain, and enhance tissue flexibility.</p>
      
    ` 
  },
  neurological: {
    title: "Neurological Physiotherapy",
    desc: `
      <p>Focuses on conditions affecting the nervous system, like stroke, Parkinson's, or spinal cord injuries.</p>
      <img src="images/treatment.jpg" alt="Assessment and Diagnosis" class="section-image">

      <p><strong class="section-heading">Comprehensive Assessment:</strong><br>
      we conduct detailed assessments to 
      understand the specific impairments and functional limitations associated with neurological conditions. This may involve evaluating motor control, 
      balance, coordination, muscle strength, sensation, and the impact on 
      activities of daily living.</p>

      <p><strong class="section-heading">Adaptive Equipment and Assistive Devices:</strong><br>
      we may recommend and train individuals in the use of adaptive equipment and assistive devices to enhance independence in daily activities.</p>
           
      <p><strong class="section-heading">Education and Support:</strong><br>
      Patients and their caregivers are provided with education and support to better understand the nature of the 
      neurological condition, manage symptoms, and promote long-term self-management.</p>
      
    ` 
  },
  cardiorespiratory: {
    title: "Cardiorespiratory Physiotherapy",
    desc: `
      <p>Deals with heart and lung conditions, aiding in rehabilitation after surgeries or respiratory illnesses.</p>

      <img src="images/fitness.avif" alt="Assessment and Diagnosis" class="section-image">
      <p><strong class="section-heading">Respiratory Muscle Training:</strong><br>
      Individuals with respiratory conditions such as chronic obstructive pulmonary disease (COPD), asthma, or post-surgery may benefit from 
      respiratory muscle training. This involves exercises to strengthen and improve the endurance of the muscles involved in 
      breathing.</p>

      <p><strong class="section-heading">Oxygen Therapy Management:</strong><br>
      we do assessment and management of oxygen therapy for individuals who require supplemental oxygen to support 
      respiratory function.</p>
            
      <p><strong class="section-heading">Cardiac Rehabilitation:</strong><br>
       Individuals recovering from cardiac events such as heart attacks or cardiac surgeries may undergo cardiac 
       rehabilitation programs. we play a key role in designing exercise programs, monitoring vital signs, 
       and providing education on heart-healthy lifestyles.</p>
      
      <p>Patient Education: Education is  a crucial cardiorespiratory physiotherapy. Patients are educated about their 
      conditions, medications, symptom management, and lifestyle modifications to promote self-management</p> 

    ` 
  },
  geriatric: {
    title: "Geriatric Physiotherapy",
    desc: `
      <p>Tailored for older adults, aiming to improve mobility, balance, and overall well-being.</p>
      <img src="images/ple.avif" alt="Assessment and Diagnosis" class="section-image">

      <p><strong class="section-heading">Functional Assessment:</strong><br>
       we conduct comprehensive assessments to evaluate an older individual's physical function, mobility, strength,
       balance, and flexibility. This helps identify areas of concern and establishes a baseline for intervention</p>

      <p><strong class="section-heading">Fall Prevention:</strong><br>
      Falls are a significant risk for older adults, often leading to injuries and decreased mobility. Geriatric physiotherapy 
      focuses on exercises and interventions to improve balance,coordination, and strength, reducing the risk of falls. .</p>
            
      <p><strong class="section-heading">Gait Training:</strong><br>
       Maintaining safe and efficient walking patterns is crucial for older adults.Physiotherapists work on 
       gait training to improve walking speed, stride length, and overall mobility. </p>
      
      <p>Pain Management: Geriatric physiotherapy addresses musculoskeletal pain, arthritis, and other 
      conditions that may contribute to discomfort or reduced mobility in older individuals.</p> 
    ` 
  },
  sports: {
    title: "Sports Physiotherapy",
    desc: `
      <p>Targets athletes' injuries, enhances performance, and aids in sports-related rehabilitation.</p>
      <img src="images/knee.avif" alt="Assessment and Diagnosis" class="section-image">

      <p><strong class="section-heading">Injury Assessment and Diagnosis:</strong><br>
       Sports physiotherapists assess and diagnose sports-related injuries, considering factors such as 
        the mechanism of injury, tissue involved, and the athlete's overall health and fitness.</p>
      
      <p><strong class="section-heading">Therapeutic Exercise:</strong><br>
      Prescribing exercises that target specific muscle groups and movement patterns is fundamental in sports physiotherapy. 
      These exercises aim to improve strength, endurance, flexibility, and neuromuscular control.</p>
           
      
    ` 
  },
  orthopedic: {
    title: "Orthopedic Physiotherapy",
    desc: `
      <p>Focuses on orthopedic conditions, such as fractures,arthritis,rehabilitation. </p>
      <img src="images/adipometer.jpg" alt="Assessment and Diagnosis" class="section-image">

      <p><strong class="section-heading">Comprehensive Assessment:</strong><br>
       Our Physiotherapists conduct thorough assessments to diagnose and understand the nature of 
       musculoskeletal conditions. This includes evaluating joint range of motion, muscle strength, 
       flexibility, posture, and functional movement patterns. </p>
      
      <p><strong class="section-heading">Pain Management:</strong><br>
      Orthopedic physiotherapy includes strategies for managing pain associated with musculoskeletal conditions. This may involve 
      the use of modalities such as heat, cold therapy, ultrasound, or electrical stimulation.</p>
      

      <p><strong class="section-heading">Rehabilitation after Surgery:</strong><br>
       Individuals who have undergone orthopedic surgeries, such as joint replacements or ligament repairs, often require 
       physiotherapy for post-surgical rehabilitation.This includes exercises to regain strength, mobility, and function.</p>
      
    ` 
  },
  ergonomictraining: {
    title: "Corporate Ergonomic Training",
    desc: `
      <p>Corporate ergonomic training is designed to educate employees about proper ergonomics in the workplace, with the goal of improving 
      comfort, health, and productivity while reducing the risk of musculoskeletal disorders. Ergonomics is the study of how people 
      interact with their work environment, and effective training can help employees understand how to set up their workstations and perform 
      tasks in a way that minimizes the risk of injuries and discomfort. Here are some key components often included in corporate ergonomic 
      training </p>
      <img src="images/assessment.jpg" alt="Assessment and Diagnosis" class="section-image">


      <strong class="section-heading">Workstation Setup:</strong><br>
       <ul>
          <li>We train on Proper arrangement of desks, chairs, monitors, keyboards, and other tools to promote 
              good posture and reduce strain. </li>
          <li>Adjusting chair height, desk height, and monitor position to suit individual needs</li>
       </ul>
      
      <strong class="section-heading">Body Mechanics:</strong><br>
       <ul>
          <li> Training on proper body mechanics and postures to adopt during various tasks to prevent strain 
               and injury.</li>
          <li>Techniques for lifting, carrying, and moving objects safely.</li>
       </ul>      

      <strong class="section-heading">Breaks and Movement:</strong><br>
       <ul>
          <li>Emphasis on the importance of taking regular breaks and incorporating movement into the workday to 
              prevent prolonged periods of static posture. </li>
          <li>Stretching exercises to relieve tension and improve flexibility.</li>
       </ul>
      
    
    ` 
  },
  pediatric: {
    title: "Pediatric Physiotherapy",
    desc: `
      <p>Pediatric physiotherapy, also known as pediatric physical therapy, is a specialized 
          branch of physical therapy focused on the assessment and treatment of infants,children, and adolescents. Pediatric physiotherapists work with young individuals 
          who may have congenital or acquired conditions affecting their physical development, motor skills, and overall functional abilities. The goal is to promote 
          optimal physical development, improve motor skills, enhance independence, and support participation in age-appropriate activities </p>
      <img src="images/child.avif" alt="Assessment and Diagnosis" class="section-image">
      <p><strong class="section-heading">Developmental Assessment:</strong><br>
       we assess the motor and musculoskeletal development of infants and children to identify any
       delays, abnormalities, or conditions affecting their physical abilities</p>      

      <p><strong class="section-heading">Early Intervention:</strong><br>
        For infants and young children, early intervention is crucial to address developmental delays or issues 
        promptly. Physiotherapists work with families and other healthcare professionals to implement interventions that 
        support normal development</p>
        
    ` 
  },
  lamaze: {
    title: "Lamaze for Expectant Mothers",
    desc: `
      <p>Lamaze is a popular approach to childbirth education that focuses on empowering expectant mothers and their partners to make informed decisions 
         about their birthing experience. The Lamaze method emphasizes a woman's natural ability to give birth and encourages a healthy, low-intervention 
         approach</p>
      <img src="images/pgnt.avif" alt="Assessment and Diagnosis" class="section-image">

      <p><strong class="section-heading">Breastfeeding Support:</strong><br>
       Lamaze classes often include information on breastfeeding and provide guidance on initiating breastfeeding during the 
       postpartum period</p>      

      <p><strong class="section-heading">Movement and Positioning:</strong><br>
      Lamaze encourages women to move and change positions during labor to find the most comfortable and effective positions </p>      
      
      <p><strong class="section-heading">Partner Involvement:</strong><br>
       Partners are actively involved in the birthing process, providing support, encouragement, and assistance.</p>
      
    ` 
  },
  homebasedcare: {
    title: "Home-Based Physiotherapy",
    desc: `
      <p>At Mephys Physiotherapy Clinic, we believe that recovery should be accessible, convenient, and compassionate. 
         That’s why our Home-Based Care Services are designed to bring expert care to your doorstep—tailored to your 
         specific needs, delivered by professionals who care.</p>
        <img src="images/care.avif" alt="Assessment and Diagnosis" class="section-image">


      <strong class="section-heading">Physiotherapy at Home</strong><br>      
       <ul>
          <li>Stroke and neurological rehab</li>
          <li>Joint pain and arthritis treatment</li>
          <li>Chest physiotherapy for breathing difficulties</li>
          <li>Pediatric physiotherapy (e.g., cerebral palsy)</li>
          <li>Back pain and postural correction</li>
       </ul>
      
      <strong class="section-heading">Post-Surgical Rehabilitation</strong><br>
       <ul>
          <li>Orthopedic recovery (fractures, joint replacements)</li>
          <li>Neurosurgical rehab</li>
          <li>Post-plastic surgery lymphatic drainage to reduce swelling and promote healing</li>
       </ul>
             
       <strong class="section-heading">Geriatric Home Care</strong><br>
       <ul>
          <li>Fall prevention and mobility training</li>
          <li>Strength and balance programs</li>
          <li>Home modification advice</li>
          <li>Family and caregiver education</li>
       </ul>
      
      <strong class="section-heading">Skilled Nursing Support</strong><br>
       <ul>
          <li>Wound care and pressure sore management</li>
          <li>Catheter and colostomy care</li>
          <li>Medication supervision</li>
          <li>Vitals and chronic condition monitoring</li>
          <li>Diabetic foot care</li>
       </ul>
      
      <strong class="section-heading">Occupational Therapy</strong><br>
       <ul>
          <li>Daily activity retraining (e.g., bathing, dressing)</li>
          <li>Assistive device prescription and training</li>
          <li>Home redesign for accessibility</li>
          <li>Ergonomic evaluations and safety audits</li>
       </ul>
      

      <strong class="section-heading">Neurological Rehabilitation</strong><br>
       <ul>
          <li>Parkinson’s disease</li>
          <li>Multiple sclerosis</li>
          <li>Stroke recovery</li>
          <li>Traumatic brain injury</li>
       </ul>
      
      <strong class="section-heading">Palliative & End-of-Life Support</strong><br>
       <ul>
          <li>Pain and symptom management</li>
          <li>Gentle physiotherapy for comfort</li>
          <li>Emotional and family support</li>          
       </ul>     


      <strong class="section-heading">Cardiopulmonary Rehabilitation</strong><br>
       <ul>
          <li>Recovery after heart surgery or stroke</li>
          <li>Breathing exercises</li>
          <li>Energy conservation strategies</li>          
       </ul>
     <strong class="section-heading">Why Choose Mephys for Home-Based Care?</strong>    
      <ul>
          <li>Multidisciplinary team: physiotherapists, nurses, and occupational therapists</li>
          <li>Personalized care plans</li>
          <li>Flexible scheduling</li> 
          <li>Safe, respectful, and compassionate service</li>
          <li>Follow-up and remote support options</li> 
       </ul>

    ` 
  },

};

const modal = document.getElementById('modal');
const modalTitle = document.getElementById('modalTitle');
const modalDesc = document.getElementById('modalDesc');
const closeModal = document.getElementById('closeModal');
let previousFocus = null;

function hideServiceModal() {
    if (!modal) return;
    modal.style.display = 'none';
    modal.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
    if (previousFocus && previousFocus.isConnected) previousFocus.focus();
}

function showServiceModal(card) {
    if (!modal || !modalTitle || !modalDesc) return;
    const key = card.getAttribute('data-service');
    const detail = serviceContent[key];
    const cardTitle = card.querySelector('.card-title');
    const cardText = card.querySelector('.card-text');
    previousFocus = card;
    modalTitle.textContent = detail ? detail.title : (cardTitle ? cardTitle.textContent.trim() : 'Physiotherapy service');
    // Static, developer-authored HTML only. Fall back to the public service-card description.
    if (detail) {
        modalDesc.innerHTML = detail.desc;
    } else {
        modalDesc.replaceChildren();
        const paragraph = document.createElement('p');
        paragraph.textContent = cardText && cardText.textContent.trim() !== '...'
          ? cardText.textContent.trim()
          : 'Contact our team to find out more about this service or request an appointment.';
        modalDesc.appendChild(paragraph);
    }
    modal.style.display = 'flex';
    modal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
    closeModal.focus();
}

if (modal) {
    document.querySelectorAll('.service-card').forEach(card => {
        card.addEventListener('click', () => showServiceModal(card));
        card.addEventListener('keydown', event => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                showServiceModal(card);
            }
        });
    });
    closeModal.addEventListener('click', hideServiceModal);
    modal.addEventListener('click', event => { if (event.target === modal) hideServiceModal(); });
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && modal.style.display === 'flex') hideServiceModal();
    });
}

/* Book Appointment Button */

// Open form from modal
document.getElementById("openAppointmentFormBtn").addEventListener("click", function () {

    const selectedService = document.getElementById("modalTitle").innerText.trim();

    document.getElementById("selectedService").value = selectedService;

    hideServiceModal();

    const formContainer = document.getElementById("appointmentFormContainer");

    formContainer.style.display = "block";
    formContainer.scrollIntoView({ behavior: "smooth" });
});


// Block dates before the earliest permitted booking date
const appointmentDate = document.getElementById("appointmentDate");
const earliestDate = appointmentDate.getAttribute("min");


// Set appointment time limits
const appointmentTime = document.getElementById("appointmentTime");

appointmentTime.min = "09:00";
appointmentTime.max = "16:00";


// Form validation and submission
document.getElementById("appointmentForm").addEventListener("submit", function (e) {

    e.preventDefault();

    const name = document.getElementById("fullName").value.trim();
    const email = document.getElementById("email").value.trim();
    const phone = document.getElementById("phone").value.trim();
    const time = appointmentTime.value.trim();
    const date = appointmentDate.value;

    const nameError = document.getElementById("nameError");
    const emailError = document.getElementById("emailError");
    const phoneError = document.getElementById("phoneError");
    const timeError = document.getElementById("timeError");

    // Reset error messages
    nameError.textContent = "";
    emailError.textContent = "";
    phoneError.textContent = "";
    timeError.textContent = "";

    let isValid = true;

    // Validate selected service
    const selectedService = document.getElementById("selectedService").value.trim();

    if (!selectedService) {
        clinicAlert.fire({
            icon: 'error',
            title: 'Service Required',
            text: 'Please select a service before booking.'
        });

        return;
    }

    // Validate full name
    if (!/^[A-Za-z\s]{2,}$/.test(name)) {
        nameError.textContent = "Please enter a valid full name.";
        isValid = false;
    }

    // Validate email
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        emailError.textContent = "Please enter a valid email address.";
        isValid = false;
    }

    // Validate phone number
    if (!/^\+?[0-9]{7,15}$/.test(phone)) {
        phoneError.textContent = "Please enter a valid phone number.";
        isValid = false;
    }

    // Validate appointment date
    if (!date || (earliestDate && date < earliestDate)) {
        clinicAlert.fire({
            icon: 'error',
            title: 'Invalid Appointment Date',
            text: 'Please select tomorrow or a future date.'
        });

        isValid = false;
    }

    // Validate appointment time
    if (!time || time < "09:00" || time > "16:00") {
        timeError.textContent = "Please select a time between 9 AM and 4 PM.";
        isValid = false;
    }

    if (!isValid) {
        return;
    }

    const form = document.getElementById("appointmentForm");

    // Collect all form fields
    const formData = new FormData(form);

    // Explicitly include the selected service
    formData.set("service", selectedService);


    // Disable submit button to prevent repeated submissions
    const submitButton = form.querySelector('[type="submit"]');

    if (submitButton) {
        submitButton.disabled = true;
    }

    // Show SweetAlert loading
    clinicAlert.fire({
        title: 'Booking your appointment...',
        text: 'Please wait a moment.',
        allowOutsideClick: false,
        didOpen: () => {
            clinicAlert.showLoading();
        }
    });

    fetch("book_appointment.php", {
        method: "POST",
        body: formData
    })
    .then((response) => response.text())
    .then((responseText) => {

        clinicAlert.close();

        let data;

        try {
            data = JSON.parse(responseText);
        } catch (error) {
            console.error("Server response:", responseText);

            clinicAlert.fire({
                icon: 'error',
                title: 'Submission Failed',
                text: 'The server returned an unexpected response. Please try again.'
            });

            return;
        }

        if (data.success === true) {

            clinicAlert.fire({
                icon: 'success',
                title: 'Request Received!',
                text: data.message || 'Thank you! We will contact you shortly to confirm.'
            });

            form.reset();

            // Restore refreshed CSRF token
            const csrfField = form.querySelector('[name="csrf_token"]');

            if (csrfField && data.csrf_token) {
                csrfField.value = data.csrf_token;
            }

            document.getElementById("appointmentFormContainer").style.display = "none";

        } else {

            clinicAlert.fire({
                icon: 'error',
                title: 'Submission Failed',
                text: data.message || 'Something went wrong. Please try again.'
            });
        }
    })
    .catch((error) => {

        clinicAlert.close();

        clinicAlert.fire({
            icon: 'error',
            title: 'Network Error',
            text: 'Unable to submit. Please check your internet connection or try again.'
        });

        console.error("Error:", error);
    })
    .finally(() => {

        if (submitButton) {
            submitButton.disabled = false;
        }
    });
});


// WhatsApp Toast
document.addEventListener("DOMContentLoaded", function () {

    const toastElement = document.getElementById('whatsappToast');

    if (toastElement && window.bootstrap && window.bootstrap.Toast) {
        const toast = new bootstrap.Toast(toastElement, {
            delay: 7000
        });

        setTimeout(() => toast.show(), 3000);
    }
});

const serviceContent = {
  musculoskeletal: {
    title: "Musculoskeletal Physiotherapy",
    desc: `
      <p>Musculoskeletal physiotherapy, also known as orthopedic physiotherapy, is a specialized branch of physical therapy that focuses on the assessment, diagnosis, and treatment of musculoskeletal conditions affecting the muscles, bones, joints, ligaments, and tendons. We work to restore and optimize the function of the musculoskeletal system, alleviate pain, and improve overall mobility and quality of life for our patients.</p>

      <p><strong class="section-heading">Assessment and Diagnosis:</strong><br>
      We conduct thorough assessments to identify the underlying causes of musculoskeletal issues. This may involve evaluating posture, joint range of motion, muscle strength, flexibility, and functional movement patterns.</p>
      <img src="images/assessment.jpg" alt="Assessment and Diagnosis" class="section-image">

      <p><strong class="section-heading">Treatment Planning:</strong><br>
      Based on the assessment findings, we develop personalized treatment plans. These plans often include a combination of therapeutic exercises, manual therapy, and other modalities to address specific issues and achieve therapeutic goals.</p>
      <img src="images/assessment.jpg" alt="Treatment Planning" class="section-image">
      
      <p><strong class="section-heading">Manual Therapy:</strong><br>
      We use hands-on techniques such as joint mobilization, soft tissue massage, and stretching to improve joint mobility, reduce pain, and enhance tissue flexibility.</p>
      <img src="images/assessment.jpg" alt="Manual Therapy" class="section-image">
    ` 
  },
  neurological: {
    title: "Neurological Physiotherapy",
    desc: `
      <p>Focuses on conditions affecting the nervous system, like stroke, Parkinson's, or spinal cord injuries.</p>

      <p><strong class="section-heading">Comprehensive Assessment:</strong><br>
      we conduct detailed assessments to 
      understand the specific impairments and functional limitations associated with neurological conditions. This may involve evaluating motor control, 
      balance, coordination, muscle strength, sensation, and the impact on 
      activities of daily living.</p>

      <img src="images/assessment.jpg" alt="Assessment and Diagnosis" class="section-image">

      <p><strong class="section-heading">Adaptive Equipment and Assistive Devices:</strong><br>
      we may recommend and train individuals in the use of adaptive equipment and assistive devices to enhance independence in daily activities.</p>
      <img src="images/assessment.jpg" alt="Treatment Planning" class="section-image">
      
      <p><strong class="section-heading">Education and Support:</strong><br>
      Patients and their caregivers are provided with education and support to better understand the nature of the 
      neurological condition, manage symptoms, and promote long-term self-management.</p>
      <img src="images/assessment.jpg" alt="Manual Therapy" class="section-image">
    ` 
  },
  cardiorespiratory: {
    title: "cardiorespiratory Physiotherapy",
    desc: `
      <p>Deals with heart and lung conditions, aiding in rehabilitation after surgeries or respiratory illnesses.</p>

      <p><strong class="section-heading">Respiratory Muscle Training:</strong><br>
      Individuals with respiratory conditions such as chronic obstructive pulmonary disease (COPD), asthma, or post-surgery may benefit from 
      respiratory muscle training. This involves exercises to strengthen and improve the endurance of the muscles involved in 
      breathing.</p>

      <img src="images/assessment.jpg" alt="Assessment and Diagnosis" class="section-image">

      <p><strong class="section-heading">Oxygen Therapy Management:</strong><br>
      we do assessment and management of oxygen therapy for individuals who require supplemental oxygen to support 
      respiratory function.</p>
      <img src="images/assessment.jpg" alt="Treatment Planning" class="section-image">
      
      <p><strong class="section-heading">Cardiac Rehabilitation:</strong><br>
       Individuals recovering from cardiac events such as heart attacks or cardiac surgeries may undergo cardiac 
       rehabilitation programs. we play a key role in designing exercise programs, monitoring vital signs, 
       and providing education on heart-healthy lifestyles.</p>
      <img src="images/assessment.jpg" alt="Manual Therapy" class="section-image">

      <p>Patient Education: Education is  a crucial cardiorespiratory physiotherapy. Patients are educated about their 
      conditions, medications, symptom management, and lifestyle modifications to promote self-management</p> 

    ` 
  },
  geriatric: {
    title: "Geriatric Physiotherapy",
    desc: `
      <p>Tailored for older adults, aiming to improve mobility, balance, and overall well-being.</p>

      <p><strong class="section-heading">Functional Assessment:</strong><br>
       we conduct comprehensive assessments to evaluate an older individual's physical function, mobility, strength,
       balance, and flexibility. This helps identify areas of concern and establishes a baseline for intervention</p>

      <img src="images/assessment.jpg" alt="Assessment and Diagnosis" class="section-image">

      <p><strong class="section-heading">Fall Prevention:</strong><br>
      Falls are a significant risk for older adults, often leading to injuries and decreased mobility. Geriatric physiotherapy 
      focuses on exercises and interventions to improve balance,coordination, and strength, reducing the risk of falls. .</p>
      <img src="images/assessment.jpg" alt="Treatment Planning" class="section-image">
      
      <p><strong class="section-heading">Gait Training:</strong><br>
       Maintaining safe and efficient walking patterns is crucial for older adults.Physiotherapists work on 
       gait training to improve walking speed, stride length, and overall mobility. </p>
      <img src="images/assessment.jpg" alt="Manual Therapy" class="section-image">

      <p>Pain Management: Geriatric physiotherapy addresses musculoskeletal pain, arthritis, and other 
      conditions that may contribute to discomfort or reduced mobility in older individuals.</p> 
    ` 
};

const modal = document.getElementById('modal');
const modalTitle = document.getElementById('modalTitle');
const modalDesc = document.getElementById('modalDesc');
const closeModal = document.getElementById('closeModal');

document.querySelectorAll('.service-card').forEach(card => {
  card.addEventListener('click', () => {
    const key = card.getAttribute('data-service');
    if(serviceContent[key]){
      modalTitle.textContent = serviceContent[key].title;
      modalDesc.innerHTML = serviceContent[key].desc;
      modal.style.display = 'flex';
    }else{
      alert("Detailed information is coming soon!");
    }
  });
});

closeModal.onclick = () => {
  modal.style.display = 'none';
};

window.onclick = (e) => {
  if (e.target === modal) modal.style.display = 'none';
};




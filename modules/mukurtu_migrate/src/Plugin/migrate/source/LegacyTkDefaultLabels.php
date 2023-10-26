<?php

namespace Drupal\mukurtu_migrate\Plugin\migrate\source;

use Drupal\migrate\Plugin\migrate\source\SourcePluginBase;
use Drupal\migrate\Plugin\migrate\source\SqlBase;
use Drupal\migrate\Row;

/**
 *
 * @MigrateSource(
 *   id = "mukurtu_v3_legacy_tk_default_labels"
 * )
 */
class LegacyTkDefaultLabels extends SqlBase
{
  protected $labelTextMapping = [
    "mukurtu_custom_TK_Attribution_Label_(TK_A)" => "This Label is being used to correct historical mistakes or exclusions pertaining to this material. This is especially in relation to the names of the people involved in performing or making this work and/or correctly naming the community from which it originally derives. As a user you are being asked to also apply the correct attribution in any future use of this work.",
    "mukurtu_custom_TK_Clan_(TK_CL)" => "This Label is being used to indicate that this material is traditionally and usually not publicly available. The Label lets future users know that this material has specific conditions for use and sharing because of clan membership and/or relationships. This material is not, and never was, free, public, or available for everyone. This Label asks viewers of these materials to respect the cultural values and expectations about circulation and use defined by designated clans, members, and their internal relations.",
    "mukurtu_custom_TK_Commercial_Label_(TK_C)" => "This material is available for commercial use. While the source community does not have copyright ownership of this material, it may still be protected under copyright and any commercial use will need to be cleared with the copyright holder. Regardless of the copyright ownership, you are asked to pay special attention to the community’s protocols and not use this material in any way that could constitute derogatory treatment and/or any other use that could constitute community or cultural harm. Where necessary, contact information is provided to help you enter into a dialogue with the original custodians and to clarify that your use will not be derogatory or cause cultural offense.",
    "mukurtu_custom_TK_Community_Use_Only_Label_(TK_CO)" => "This Label is being used to indicate that this material is traditionally and usually not publicly available. The Label is correcting a misunderstanding about the circulation options for this material and letting any users know that this material has specific conditions for circulation within the community. It is not, and never was, free, public, or available for everyone at anytime. This Label asks you to think about how you are going to use this material and to respect different cultural values and expectations about circulation and use.",
    "mukurtu_custom_TK_Community_Voice_(TK_CV)" => "This Label is being used to encourage the sharing of stories and voices about this material. The Label indicates that existing knowledge or descriptions are incomplete or partial. Any community member is invited and welcome to contribute to our community knowledge about this event, photograph, recording or heritage item. Sharing our voices helps us reclaim our histories and knowledge. This sharing is an internal process.",
    "mukurtu_custom_TK_Culturally_Sensitive_(TK_CS)" => "This Label is being used to indicate that this material has cultural and/or historical sensitivities. The Label asks for care to be taken when this material is accessed, used, and circulated, especially when materials are first returned or reunited with communities of origin. In some instances, this Label will indicate that there are specific permissions for use of this material required directly from the community itself.",
    "mukurtu_custom_TK_Family_Label_(TK_F)" => "This Label is being used to indicate that this material is traditionally and usually not publicly available. The Label is correcting a misunderstanding about the circulation options for this material and letting any users know that this material has specific conditions for sharing between family members. Who these family members are, and how sharing occurs will be defined in each locale. This material is not, and never was, free, public, or available for everyone at anytime. This Label asks you to think about how you are going to use this material and to respect different cultural values and expectations about circulation and use.",
    "mukurtu_custom_TK_Men_General_Label_(TK_MG)" => "This material has specific gender restrictions on access. It is usually only to be accessed and used by men in the community. If you are not from the community and you have accessed this material, you are requested to not download, copy, remix or otherwise circulate this material to others without permission. This Label asks you to think about whether you should be using this material and to respect different cultural values and expectations about circulation and use.",
    "mukurtu_custom_TK_Men_Restricted_Label_(TK_MR)" => "This material has specific gender restrictions on access. It is regarded as important secret and/or ceremonial material that has community-based laws in relation to who can access it. Given its nature it is only to be accessed and used by authorized [and/or initiated] men in the community. If you are an external third party user and you have accessed this material, you are requested to not download, copy, remix or otherwise circulate this material to others. This material is not freely available within the community and it therefore should not be considered freely available outside of the community. This Label asks you to think about whether you should be using this material and to respect different cultural values and expectations about circulation and use.",
    "mukurtu_custom_TK_Multiple_Communities_(TK_MC)" => "Responsibility and ownership over this material is spread across several distinct communities. Use will be dependent upon discussion and negotiation with the multiple communities named herein [insert names]. Decisions about use will need to be decided collectively. As an external user of this material you are asked to recognize and respect cultural protocols in relation to the use of this material and clear your intended use with the relevant communities.",
    "mukurtu_custom_TK_Non-Commercial_Label_(TK_NC)" => "This material has been designated as being available for non-commercial use. You are allowed to use this material for non-commercial purposes including for research, study, or public presentation and/or online in blogs or non-commercial websites. This Label asks you to think and act with fairness and responsibility towards this material and the original custodians.",
    "mukurtu_custom_TK_Non-Verified_(TK_NV)" => "This Label is being used because there are concerns about accuracy and/or representations made in this material. This material was not created through informed consent or community protocols for research and engagement. Therefore, questions about its accuracy and who/how it represents this community are being raised.",
    "mukurtu_custom_TK_Outreach_Label_(TK_O)" => "This Label is being used to indicate that this material is traditionally and usually not publicly available. The Label is correcting a misunderstanding about the circulation options for this material and letting any users know that this material can be used for educational outreach activities. This Label asks you to respect the designated circulation conditions for this material and additionally, where possible, to develop a means for fair and equitable reciprocal exchange for the use of this material with the relevant TK holders. This exchange might include access to educational or other resources that are difficult to access under normal circumstances.",
    "mukurtu_custom_TK_Seasonal_Label_(TK_S)" => "This Label is being used to indicate that this material traditionally and usually is heard and/or utilized at a particular time of year and in response to specific seasonal changes and conditions. For instance, many important ceremonies are held at very specific times of the year. This Label is being used to indicate sophisticated relationships between land and knowledge creation. It is also being used to highlight the relationships between recorded material and the specific contexts where it derives, especially the interconnected and embodied teachings that it conveys.",
    "mukurtu_custom_TK_Secret/Sacred_Label_(TK_SS)" => "This Label is being used to indicate that this material is traditionally and usually not publicly available because it contains important secret or sacred components. The Label is correcting a misunderstanding about the significance of this material and therefore its circulation conditions. It is letting users know that because of its secret/sacred status it is not, and was never free, public, or available for everyone at anytime. This Label asks you to think about whether you should be using this material and to respect different cultural values and expectations about circulation and use.",
    "mukurtu_custom_TK_Verified_Label_(TK_V)" => "This Label affirms that the representation and presentation of this material is in keeping with community expectations and cultural protocols. It lets you know that for the individual, family or community represented in this material, use is considered fair, reasonable and respectful.",
    "mukurtu_custom_TK_Women_General_Label_(TK_WG)" => "This material has specific gender restrictions on access. It is usually only to be accessed and used by women in the community. If you are not from the community and you have accessed this material, you are requested not to download, copy, remix or otherwise circulate this material to others without permission. This Label asks you to think about whether you should be using this material and to respect different cultural values and expectations about circulation and use.",
    "mukurtu_custom_TK_Women_Restricted_Label_(TK_WR)" => "This material has specific gender restrictions on access. It is regarded as important secret and/or ceremonial material that has community-based laws in relation to who can access it. Given its nature it is only to be accessed and used by authorized [and/or initiated] women in the community. If you are an external third party user and you have accessed this material, you are requested to not download, copy, remix or otherwise circulate this material to others. This material is not freely available within the community and it therefore should not be considered freely available outside of the community. This Label asks you to think about whether you should be using this material and to respect different cultural values and expectations about circulation and use.",
  ];

  /**
   * {@inheritdoc}
   */
  public function fields()
  {
    return [
      'name' => $this->t('Variable name'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getIds()
  {
    return ['name' => ['type' => 'string']];
  }

  /**
   * {@inheritdoc}
   */
  public function query()
  {
    $fields = array_keys($this->fields());
    $query = $this->select('variable', 'v')
      ->fields('v', $fields)
      ->condition('name', 'mukurtu_custom_TK_%', 'LIKE');
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row)
  {
    $row->setSourceProperty('project_id', 'default_tk');
    $row->setSourceProperty('default_text', $this->labelTextMapping[$row->getSourceProperty('name')]);

    $intermediate_label_name = str_replace('mukurtu_custom_', '', $row->getSourceProperty('name'));
    $cleaned_label_name = str_replace('_', ' ', $intermediate_label_name);
    $row->setSourceProperty('name', $cleaned_label_name);

    $labelInitials = $this->getLabelInitials($cleaned_label_name);

    $row->setSourceProperty('id', 'default_tk_' . $labelInitials);

    $row->setSourceProperty('img_url', $this->buildUrl('img', $labelInitials));
    $row->setSourceProperty('svg_url', $this->buildUrl('svg', $labelInitials));
    $row->setSourceProperty('audio_url', '');

    $langcode = \Drupal::languageManager()->getCurrentLanguage()->getId();
    $language = \Drupal::languageManager()->getCurrentLanguage()->getName();
    $row->setSourceProperty('locale', $langcode);
    $row->setSourceProperty('language', $language);

    $row->setSourceProperty('community', 'N/A');
    $row->setSourceProperty('type', 'Legacy');
    $row->setSourceProperty('display', 'label');
    $row->setSourceProperty('tk_or_bc', 'tk');

    $row->setSourceProperty('updated', time());

    return parent::prepareRow($row);
  }

  protected function getLabelInitials($labelName)
  {
    $toks = explode('(', $labelName);
    $toks = explode(')', $toks[1]);
    $toks = explode(' ', $toks[0]);
    return strtolower($toks[1]);
  }

  protected function buildUrl($type, $labelLetters)
  {
    $baseUrl = 'https://raw.githubusercontent.com/kimberlychristen/Local-Contexts/master/';
    $semiBuiltUrl = $baseUrl . $labelLetters . '/label_' . $labelLetters;
    if ($type == 'img') {
      return $semiBuiltUrl . '.png';
    } else if ($type == 'svg') {
      return $semiBuiltUrl . '.svg';
    }
  }
}
